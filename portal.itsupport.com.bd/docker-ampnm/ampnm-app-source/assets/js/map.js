function initMap() {
    // Initialize all modules
    MapApp.ui.cacheElements();

    const {
        els
    } = MapApp.ui;
    const {
        api
    } = MapApp;
    const {
        state
    } = MapApp;
    const {
        mapManager
    } = MapApp;
    const {
        deviceManager
    } = MapApp;

    // Add new elements to cache
    els.mapSettingsBtn = document.getElementById('mapSettingsBtn');
    els.mapSettingsModal = document.getElementById('mapSettingsModal');
    els.mapSettingsForm = document.getElementById('mapSettingsForm');
    els.cancelMapSettingsBtn = document.getElementById('cancelMapSettingsBtn');
    els.resetMapBgBtn = document.getElementById('resetMapBgBtn');
    els.mapBgUpload = document.getElementById('mapBgUpload');
    els.placeDeviceBtn = document.getElementById('placeDeviceBtn');
    els.placeDeviceModal = document.getElementById('placeDeviceModal');
    els.closePlaceDeviceModal = document.getElementById('closePlaceDeviceModal');
    els.placeDeviceList = document.getElementById('placeDeviceList');
    els.placeDeviceLoader = document.getElementById('placeDeviceLoader');

    // Cleanup function for SPA navigation
    window.cleanup = () => {
        if (state.animationFrameId) {
            cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }
        Object.values(state.pingIntervals).forEach(clearInterval);
        state.pingIntervals = {};
        if (state.globalRefreshIntervalId) {
            clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
        }
        if (state.network) {
            state.network.destroy();
            state.network = null;
        }
        window.cleanup = null;
    };

    // Event Listeners Setup
    els.deviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(els.deviceForm);
        const data = Object.fromEntries(formData.entries());
        const id = data.id;
        delete data.id;
        data.show_live_ping = document.getElementById('showLivePing').checked;

        try {
            if (id) {
                const updatedDevice = await api.post('update_device', { id, updates: data });
                const existingNode = state.nodes.get(id);
                if (existingNode) {
                    let label = updatedDevice.name;
                    if (updatedDevice.show_live_ping && updatedDevice.status === 'online' && updatedDevice.last_avg_time !== null) {
                        label += `\n${updatedDevice.last_avg_time}ms | TTL:${updatedDevice.last_ttl || 'N/A'}`;
                    }

                    const nodeUpdate = {
                        id: updatedDevice.id,
                        label: label,
                        title: MapApp.utils.buildNodeTitle(updatedDevice),
                        deviceData: updatedDevice,
                        font: { ...existingNode.font, size: parseInt(updatedDevice.name_text_size) || 14 },
                    };

                    if (updatedDevice.icon_url) {
                        Object.assign(nodeUpdate, {
                            shape: 'image',
                            image: updatedDevice.icon_url,
                            size: (parseInt(updatedDevice.icon_size) || 50) / 2,
                            color: { border: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' },
                            borderWidth: 3
                        });
                        delete nodeUpdate.icon;
                    } else if (updatedDevice.type === 'box') {
                        Object.assign(nodeUpdate, { shape: 'box' });
                    } else {
                        Object.assign(nodeUpdate, {
                            shape: 'icon',
                            image: null,
                            icon: {
                                ...(existingNode.icon || {}),
                                face: "'Font Awesome 6 Free'", weight: "900",
                                code: MapApp.config.iconMap[updatedDevice.type] || MapApp.config.iconMap.other,
                                size: parseInt(updatedDevice.icon_size) || 50,
                                color: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown
                            }
                        });
                    }
                    
                    state.nodes.update(nodeUpdate);

                    if (existingNode.deviceData.ping_interval !== updatedDevice.ping_interval) {
                        if (state.pingIntervals[id]) {
                            clearInterval(state.pingIntervals[id]);
                            delete state.pingIntervals[id];
                        }
                        if (updatedDevice.ping_interval > 0 && updatedDevice.ip) {
                            state.pingIntervals[id] = setInterval(() => deviceManager.pingSingleDevice(id), updatedDevice.ping_interval * 1000);
                        }
                    }
                }
                window.notyf.success('Item updated.');
            } else {
                const numericFields = ['ping_interval', 'icon_size', 'name_text_size', 'warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold'];
                for (const key in data) {
                    if (numericFields.includes(key) && data[key] === '') data[key] = null;
                }
                if (data.ip === '') data.ip = null;
                
                const newDevice = await api.post('create_device', { ...data, map_id: state.currentMapId });
                
                const baseNode = {
                    id: newDevice.id, label: newDevice.name, title: MapApp.utils.buildNodeTitle(newDevice),
                    x: newDevice.x, y: newDevice.y,
                    font: { color: 'white', size: parseInt(newDevice.name_text_size) || 14, multi: true },
                    deviceData: newDevice
                };

                let visNode;
                if (newDevice.icon_url) {
                    visNode = { ...baseNode, shape: 'image', image: newDevice.icon_url, size: (parseInt(newDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[newDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
                } else if (newDevice.type === 'box') {
                    visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
                } else {
                    visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[newDevice.type] || MapApp.config.iconMap.other, size: parseInt(newDevice.icon_size) || 50, color: MapApp.config.statusColorMap[newDevice.status] || MapApp.config.statusColorMap.unknown } };
                }

                state.nodes.add(visNode);
                window.notyf.success('Item created.');
            }
            closeModal('deviceModal');
        } catch (error) {
            console.error("Failed to save device:", error);
            window.notyf.error(error.message || "An error occurred while saving.");
        }
    });

    document.getElementById('icon_upload').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        const deviceId = document.getElementById('deviceId').value;
        if (!file) return;
        if (!deviceId) {
            window.notyf.error('Please save the item before uploading an icon.');
            e.target.value = '';
            return;
        }
    
        const loader = document.getElementById('icon_upload_loader');
        loader.classList.remove('hidden');
    
        const formData = new FormData();
        formData.append('id', deviceId);
        formData.append('iconFile', file);
    
        try {
            const res = await fetch(`${MapApp.config.API_URL}?action=upload_device_icon`, {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                document.getElementById('icon_url').value = result.url;
                const previewImg = document.getElementById('icon_preview');
                previewImg.src = result.url;
                document.getElementById('icon_preview_wrapper').classList.remove('hidden');
                window.notyf.success('Icon uploaded. Press Save to apply changes.');
            } else {
                throw new Error(result.error || 'Upload failed');
            }
        } catch (error) {
            console.error('Icon upload failed:', error);
            window.notyf.error(error.message);
        } finally {
            loader.classList.add('hidden');
            e.target.value = '';
        }
    });

    els.edgeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edgeId').value;
        const connection_type = document.getElementById('connectionType').value;
        await api.post('update_edge', { id, connection_type });
        closeModal('edgeModal');
        state.edges.update({ id, connection_type, label: connection_type });
        window.notyf.success('Connection updated.');
    });

    els.scanForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const subnet = document.getElementById('subnetInput').value;
        if (!subnet) return;
        els.scanInitialMessage.classList.add('hidden');
        els.scanResults.innerHTML = '';
        els.scanLoader.classList.remove('hidden');
        try {
            const result = await api.post('scan_network', { subnet });
            els.scanResults.innerHTML = result.devices.map(device => `<div class="flex items-center justify-between p-2 border-b border-slate-700"><div><div class="font-mono text-white">${device.ip}</div><div class="text-sm text-slate-400">${device.hostname || 'N/A'}</div></div><button class="add-scanned-device-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-ip="${device.ip}" data-name="${device.hostname || device.ip}">Add</button></div>`).join('') || '<p class="text-center text-slate-500 py-4">No devices found.</p>';
        } catch (error) {
            els.scanResults.innerHTML = '<p class="text-center text-red-400 py-4">Scan failed. Ensure nmap is installed.</p>';
        } finally {
            els.scanLoader.classList.add('hidden');
        }
    });

    els.scanResults.addEventListener('click', (e) => {
        if (e.target.classList.contains('add-scanned-device-btn')) {
            const { ip, name } = e.target.dataset;
            closeModal('scanModal');
            MapApp.ui.openDeviceModal(null, { ip, name });
            e.target.textContent = 'Added';
            e.target.disabled = true;
        }
    });

    els.refreshStatusBtn.addEventListener('click', async () => {
        els.refreshStatusBtn.disabled = true;
        await deviceManager.performBulkRefresh();
        if (!els.liveRefreshToggle.checked) els.refreshStatusBtn.disabled = false;
    });

    els.liveRefreshToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            window.notyf.info(`Live status enabled. Updating every ${MapApp.config.REFRESH_INTERVAL_SECONDS} seconds.`);
            els.refreshStatusBtn.disabled = true;
            deviceManager.performBulkRefresh();
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (state.globalRefreshIntervalId) clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
            els.refreshStatusBtn.disabled = false;
            window.notyf.info('Live status disabled.');
        }
    });

    els.exportBtn.addEventListener('click', () => {
        if (!state.currentMapId) {
            window.notyf.error('No map selected to export.');
            return;
        }
        const mapName = els.mapSelector.options[els.mapSelector.selectedIndex].text;
        const devices = state.nodes.get({ fields: ['id', 'deviceData'] }).map(node => ({
            id: node.id,
            ...node.deviceData
        }));
        const edges = state.edges.get({ fields: ['from', 'to', 'connection_type'] });
        const exportData = { devices, edges };
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", `${mapName.replace(/\s+/g, '_')}_export.json`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
        window.notyf.success('Map exported successfully.');
    });

    els.importBtn.addEventListener('click', () => els.importFile.click());
    els.importFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (confirm('This will overwrite the current map. Are you sure?')) {
            const reader = new FileReader();
            reader.onload = async (event) => {
                try {
                    const data = JSON.parse(event.target.result);
                    await api.post('import_map', { map_id: state.currentMapId, ...data });
                    await mapManager.switchMap(state.currentMapId);
                    window.notyf.success('Map imported successfully.');
                } catch (err) {
                    window.notyf.error('Failed to import map: ' + err.message);
                }
            };
            reader.readText(file);
        }
        els.importFile.value = '';
    });

    els.fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) els.mapWrapper.requestFullscreen();
        else document.exitFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        const icon = els.fullscreenBtn.querySelector('i');
        icon.classList.toggle('fa-expand', !document.fullscreenElement);
        icon.classList.toggle('fa-compress', !!document.fullscreenElement);
    });

    els.newMapBtn.addEventListener('click', mapManager.createMap);
    els.createFirstMapBtn.addEventListener('click', mapManager.createMap);
    els.renameMapBtn.addEventListener('click', async () => {
        if (!state.currentMapId) {
            window.notyf.error('No map selected to rename.');
            return;
        }
        const selectedOption = els.mapSelector.options[els.mapSelector.selectedIndex];
        const currentName = selectedOption.text;
        const newName = prompt('Enter a new name for the map:', currentName);
    
        if (newName && newName.trim() !== '' && newName !== currentName) {
            try {
                await api.post('update_map', { id: state.currentMapId, updates: { name: newName } });
                selectedOption.text = newName;
                els.currentMapName.textContent = newName;
                window.notyf.success('Map renamed successfully.');
            } catch (error) {
                console.error("Failed to rename map:", error);
                window.notyf.error(error.message || "Could not rename map.");
            }
        }
    });
    els.deleteMapBtn.addEventListener('click', async () => {
        if (confirm(`Delete map "${els.mapSelector.options[els.mapSelector.selectedIndex].text}"?`)) {
            await api.post('delete_map', { id: state.currentMapId });
            const firstMapId = await mapManager.loadMaps();
            await mapManager.switchMap(firstMapId);
            window.notyf.success('Map deleted.');
        }
    });
    els.mapSelector.addEventListener('change', (e) => mapManager.switchMap(e.target.value));
    els.addDeviceBtn.addEventListener('click', () => MapApp.ui.openDeviceModal());
    els.cancelBtn.addEventListener('click', () => closeModal('deviceModal'));
    els.addEdgeBtn.addEventListener('click', () => {
        state.network.addEdgeMode();
        window.notyf.info('Click on a node to start a connection.');
    });
    els.cancelEdgeBtn.addEventListener('click', () => closeModal('edgeModal'));
    els.scanNetworkBtn.addEventListener('click', () => openModal('scanModal'));
    els.closeScanModal.addEventListener('click', () => closeModal('scanModal'));
    document.getElementById('deviceType').addEventListener('change', (e) => MapApp.ui.toggleDeviceModalFields(e.target.value));

    // Place Device Modal Logic
    els.placeDeviceBtn.addEventListener('click', async () => {
        openModal('placeDeviceModal');
        els.placeDeviceLoader.classList.remove('hidden');
        els.placeDeviceList.innerHTML = '';
        try {
            const unmappedDevices = await api.get('get_devices', { unmapped: true });
            if (unmappedDevices.length > 0) {
                els.placeDeviceList.innerHTML = unmappedDevices.map(device => `
                    <div class="flex items-center justify-between p-2 border-b border-slate-700 hover:bg-slate-700/50">
                        <div>
                            <div class="font-medium text-white">${device.name}</div>
                            <div class="text-sm text-slate-400 font-mono">${device.ip || 'No IP'}</div>
                        </div>
                        <button class="place-device-item-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-id="${device.id}">
                            Place
                        </button>
                    </div>
                `).join('');
            } else {
                els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
            }
        } catch (error) {
            console.error('Failed to load unmapped devices:', error);
            els.placeDeviceList.innerHTML = '<p class="text-center text-red-400 py-4">Could not load devices.</p>';
        } finally {
            els.placeDeviceLoader.classList.add('hidden');
        }
    });
    els.closePlaceDeviceModal.addEventListener('click', () => closeModal('placeDeviceModal'));
    els.placeDeviceList.addEventListener('click', async (e) => {
        if (e.target.classList.contains('place-device-item-btn')) {
            const deviceId = e.target.dataset.id;
            e.target.disabled = true;
            e.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const viewPosition = state.network.getViewPosition();
            const canvasPosition = state.network.canvas.DOMtoCanvas(viewPosition);

            const updatedDevice = await api.post('update_device', {
                id: deviceId,
                updates: { map_id: state.currentMapId, x: canvasPosition.x, y: canvasPosition.y }
            });

            // Add the device to the map visually
            const baseNode = {
                id: updatedDevice.id, label: updatedDevice.name, title: MapApp.utils.buildNodeTitle(updatedDevice),
                x: updatedDevice.x, y: updatedDevice.y,
                font: { color: 'white', size: parseInt(updatedDevice.name_text_size) || 14, multi: true },
                deviceData: updatedDevice
            };
            let visNode;
            if (updatedDevice.icon_url) {
                visNode = { ...baseNode, shape: 'image', image: updatedDevice.icon_url, size: (parseInt(updatedDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
            } else if (updatedDevice.type === 'box') {
                visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
            } else {
                visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[updatedDevice.type] || MapApp.config.iconMap.other, size: parseInt(updatedDevice.icon_size) || 50, color: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown } };
            }
            state.nodes.add(visNode);
            
            window.notyf.success(`Device "${updatedDevice.name}" placed on map.`);
            e.target.closest('.flex').remove(); // Remove from list
            if (els.placeDeviceList.children.length === 0) {
                els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
            }
        }
    });

    // Map Settings Modal Logic
    els.mapSettingsBtn.addEventListener('click', () => {
        const currentMap = state.maps.find(m => m.id == state.currentMapId);
        if (currentMap) {
            document.getElementById('mapBgColor').value = currentMap.background_color || '#1e293b';
            document.getElementById('mapBgColorHex').value = currentMap.background_color || '#1e293b';
            document.getElementById('mapBgImageUrl').value = currentMap.background_image_url || '';
            openModal('mapSettingsModal');
        }
    });
    els.cancelMapSettingsBtn.addEventListener('click', () => closeModal('mapSettingsModal'));
    document.getElementById('mapBgColor').addEventListener('input', (e) => {
        document.getElementById('mapBgColorHex').value = e.target.value;
    });
    document.getElementById('mapBgColorHex').addEventListener('input', (e) => {
        document.getElementById('mapBgColor').value = e.target.value;
    });
    els.mapSettingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const updates = {
            background_color: document.getElementById('mapBgColorHex').value,
            background_image_url: document.getElementById('mapBgImageUrl').value
        };
        await api.post('update_map', { id: state.currentMapId, updates });
        await mapManager.loadMaps(); // Reload maps to get fresh data
        await mapManager.switchMap(state.currentMapId); // Re-apply settings
        closeModal('mapSettingsModal');
        window.notyf.success('Map settings saved.');
    });
    els.resetMapBgBtn.addEventListener('click', async () => {
        const updates = { background_color: null, background_image_url: null };
        await api.post('update_map', { id: state.currentMapId, updates });
        await mapManager.loadMaps();
        await mapManager.switchMap(state.currentMapId);
        closeModal('mapSettingsModal');
        window.notyf.success('Map background reset to default.');
    });
    els.mapBgUpload.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const loader = document.getElementById('mapBgUploadLoader');
        loader.classList.remove('hidden');
        const formData = new FormData();
        formData.append('map_id', state.currentMapId);
        formData.append('backgroundFile', file);
        try {
            const res = await fetch(`${MapApp.config.API_URL}?action=upload_map_background`, { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                document.getElementById('mapBgImageUrl').value = result.url;
                window.notyf.success('Image uploaded. Click Save to apply.');
            } else { throw new Error(result.error); }
        } catch (error) {
            window.notyf.error('Upload failed: ' + error.message);
        } finally {
            loader.classList.add('hidden');
            e.target.value = '';
        }
    });

    // Initial Load
    (async () => {
        els.liveRefreshToggle.checked = false;
        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id');
        const firstMapId = await mapManager.loadMaps();
        const initialMapId = mapToLoad || firstMapId;
        if (initialMapId) {
            els.mapSelector.value = initialMapId;
            await mapManager.switchMap(initialMapId);
            const deviceToEdit = urlParams.get('edit_device_id');
            if (deviceToEdit && state.nodes.get(deviceToEdit)) {
                MapApp.ui.openDeviceModal(deviceToEdit);
                const newUrl = window.location.pathname + `?map_id=${initialMapId}`;
                history.replaceState(null, '', newUrl);
            }
        }
    })();
}