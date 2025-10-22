function initEmailNotifications() {
    const API_URL = 'api.php';

    const els = {
        smtpSettingsForm: document.getElementById('smtpSettingsForm'),
        smtpHost: document.getElementById('smtpHost'),
        smtpPort: document.getElementById('smtpPort'),
        smtpUsername: document.getElementById('smtpUsername'),
        smtpPassword: document.getElementById('smtpPassword'),
        smtpEncryption: document.getElementById('smtpEncryption'),
        smtpFromEmail: document.getElementById('smtpFromEmail'),
        smtpFromName: document.getElementById('smtpFromName'),
        saveSmtpBtn: document.getElementById('saveSmtpBtn'),
        smtpLoader: document.getElementById('smtpLoader'),

        deviceSelect: document.getElementById('deviceSelect'),
        subscriptionFormContainer: document.getElementById('subscriptionFormContainer'),
        selectedDeviceName: document.getElementById('selectedDeviceName'),
        deviceSubscriptionForm: document.getElementById('deviceSubscriptionForm'),
        subscriptionId: document.getElementById('subscriptionId'),
        subscriptionDeviceId: document.getElementById('subscriptionDeviceId'),
        recipientEmail: document.getElementById('recipientEmail'),
        notifyOnline: document.getElementById('notifyOnline'),
        notifyOffline: document.getElementById('notifyOffline'),
        notifyWarning: document.getElementById('notifyWarning'),
        notifyCritical: document.getElementById('notifyCritical'),
        saveSubscriptionBtn: document.getElementById('saveSubscriptionBtn'),
        cancelSubscriptionBtn: document.getElementById('cancelSubscriptionBtn'),
        
        subscriptionsTable: document.getElementById('subscriptionsTable'),
        subscriptionsLoader: document.getElementById('subscriptionsLoader'),
        noSubscriptionsMessage: document.getElementById('noSubscriptionsMessage'),
    };

    let currentSelectedDeviceId = null;

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    // --- SMTP Settings Logic ---
    const loadSmtpSettings = async () => {
        els.smtpLoader.classList.remove('hidden');
        try {
            const settings = await api.get('get_smtp_settings');
            if (settings) {
                els.smtpHost.value = settings.host || '';
                els.smtpPort.value = settings.port || '';
                els.smtpUsername.value = settings.username || '';
                els.smtpPassword.value = settings.password || ''; // Will be '********' if masked
                els.smtpEncryption.value = settings.encryption || 'tls';
                els.smtpFromEmail.value = settings.from_email || '';
                els.smtpFromName.value = settings.from_name || '';
            }
        } catch (error) {
            console.error('Failed to load SMTP settings:', error);
            window.notyf.error('Failed to load SMTP settings.');
        } finally {
            els.smtpLoader.classList.add('hidden');
        }
    };

    els.smtpSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        els.saveSmtpBtn.disabled = true;
        els.saveSmtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        const formData = new FormData(els.smtpSettingsForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const result = await api.post('save_smtp_settings', data);
            if (result.success) {
                window.notyf.success(result.message);
                // Reload settings to ensure password masking is applied
                await loadSmtpSettings();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred while saving SMTP settings.');
            console.error(error);
        } finally {
            els.saveSmtpBtn.disabled = false;
            els.saveSmtpBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Settings';
        }
    });

    // --- Device Subscriptions Logic ---
    const populateDeviceSelect = async () => {
        try {
            const devices = await api.get('get_all_devices_for_subscriptions');
            els.deviceSelect.innerHTML = '<option value="">-- Select a device --</option>' + 
                devices.map(d => `<option value="${d.id}">${d.name} (${d.ip || 'No IP'}) ${d.map_name ? `[${d.map_name}]` : ''}</option>`).join('');
        } catch (error) {
            console.error('Failed to load devices for subscriptions:', error);
            window.notyf.error('Failed to load devices for subscriptions.');
        }
    };

    const loadDeviceSubscriptions = async (deviceId) => {
        els.subscriptionsLoader.classList.remove('hidden');
        els.subscriptionsTable.innerHTML = '';
        els.noSubscriptionsMessage.classList.add('hidden');

        try {
            const subscriptions = await api.get('get_device_subscriptions', { device_id: deviceId });
            if (subscriptions.length > 0) {
                els.subscriptionsTable.innerHTML = subscriptions.map(sub => `
                    <tr class="border-b border-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap text-white">${sub.recipient_email}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400">
                            ${sub.notify_on_online ? '<span class="inline-block bg-green-500/20 text-green-400 px-2 py-1 rounded-full text-xs mr-1 mb-1">Online</span>' : ''}
                            ${sub.notify_on_offline ? '<span class="inline-block bg-red-500/20 text-red-400 px-2 py-1 rounded-full text-xs mr-1 mb-1">Offline</span>' : ''}
                            ${sub.notify_on_warning ? '<span class="inline-block bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full text-xs mr-1 mb-1">Warning</span>' : ''}
                            ${sub.notify_on_critical ? '<span class="inline-block bg-red-700/20 text-red-600 px-2 py-1 rounded-full text-xs mr-1 mb-1">Critical</span>' : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button class="edit-subscription-btn text-yellow-400 hover:text-yellow-300 mr-3" data-id="${sub.id}" data-device-id="${deviceId}" data-email="${sub.recipient_email}" data-online="${sub.notify_on_online}" data-offline="${sub.notify_on_offline}" data-warning="${sub.notify_on_warning}" data-critical="${sub.notify_on_critical}"><i class="fas fa-edit"></i> Edit</button>
                            <button class="delete-subscription-btn text-red-500 hover:text-red-400" data-id="${sub.id}"><i class="fas fa-trash"></i> Delete</button>
                        </td>
                    </tr>
                `).join('');
            } else {
                els.noSubscriptionsMessage.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to load device subscriptions:', error);
            window.notyf.error('Failed to load device subscriptions.');
        } finally {
            els.subscriptionsLoader.classList.add('hidden');
        }
    };

    const resetSubscriptionForm = () => {
        els.subscriptionId.value = '';
        els.recipientEmail.value = '';
        els.notifyOnline.checked = true;
        els.notifyOffline.checked = true;
        els.notifyWarning.checked = false;
        els.notifyCritical.checked = false;
        els.saveSubscriptionBtn.innerHTML = 'Save Subscription';
    };

    els.deviceSelect.addEventListener('change', (e) => {
        currentSelectedDeviceId = e.target.value;
        if (currentSelectedDeviceId) {
            els.subscriptionDeviceId.value = currentSelectedDeviceId;
            els.selectedDeviceName.textContent = els.deviceSelect.options[els.deviceSelect.selectedIndex].text;
            els.subscriptionFormContainer.classList.remove('hidden');
            resetSubscriptionForm();
            loadDeviceSubscriptions(currentSelectedDeviceId);
        } else {
            els.subscriptionFormContainer.classList.add('hidden');
            els.subscriptionsTable.innerHTML = '';
            els.noSubscriptionsMessage.classList.add('hidden');
        }
    });

    els.deviceSubscriptionForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        els.saveSubscriptionBtn.disabled = true;
        els.saveSubscriptionBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        const data = {
            id: els.subscriptionId.value || null,
            device_id: els.subscriptionDeviceId.value,
            recipient_email: els.recipientEmail.value,
            notify_on_online: els.notifyOnline.checked,
            notify_on_offline: els.notifyOffline.checked,
            notify_on_warning: els.notifyWarning.checked,
            notify_on_critical: els.notifyCritical.checked,
        };

        try {
            const result = await api.post('save_device_subscription', data);
            if (result.success) {
                window.notyf.success(result.message);
                resetSubscriptionForm();
                loadDeviceSubscriptions(currentSelectedDeviceId);
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred while saving subscription.');
            console.error(error);
        } finally {
            els.saveSubscriptionBtn.disabled = false;
            els.saveSubscriptionBtn.innerHTML = 'Save Subscription';
        }
    });

    els.cancelSubscriptionBtn.addEventListener('click', resetSubscriptionForm);

    els.subscriptionsTable.addEventListener('click', async (e) => {
        const editButton = e.target.closest('.edit-subscription-btn');
        const deleteButton = e.target.closest('.delete-subscription-btn');

        if (editButton) {
            els.subscriptionId.value = editButton.dataset.id;
            els.recipientEmail.value = editButton.dataset.email;
            els.notifyOnline.checked = editButton.dataset.online === '1';
            els.notifyOffline.checked = editButton.dataset.offline === '1';
            els.notifyWarning.checked = editButton.dataset.warning === '1';
            els.notifyCritical.checked = editButton.dataset.critical === '1';
            els.saveSubscriptionBtn.innerHTML = 'Update Subscription';
        } else if (deleteButton) {
            const subscriptionId = deleteButton.dataset.id;
            if (confirm('Are you sure you want to delete this subscription?')) {
                try {
                    const result = await api.post('delete_device_subscription', { id: subscriptionId });
                    if (result.success) {
                        window.notyf.success(result.message);
                        loadDeviceSubscriptions(currentSelectedDeviceId);
                    } else {
                        window.notyf.error(`Error: ${result.error}`);
                    }
                } catch (error) {
                    window.notyf.error('An unexpected error occurred while deleting subscription.');
                    console.error(error);
                }
            }
        }
    });

    // Initial load
    loadSmtpSettings();
    populateDeviceSelect();
}