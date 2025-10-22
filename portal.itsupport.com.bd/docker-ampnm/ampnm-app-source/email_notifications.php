<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">Email Notifications</h1>

        <!-- SMTP Settings Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">SMTP Server Settings</h2>
            <form id="smtpSettingsForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="smtpHost" class="block text-sm font-medium text-slate-400 mb-1">SMTP Host</label>
                        <input type="text" id="smtpHost" name="host" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smtpPort" class="block text-sm font-medium text-slate-400 mb-1">Port</label>
                        <input type="number" id="smtpPort" name="port" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smtpUsername" class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                        <input type="text" id="smtpUsername" name="username" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smtpPassword" class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" id="smtpPassword" name="password" placeholder="Leave blank to keep current" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div>
                        <label for="smtpEncryption" class="block text-sm font-medium text-slate-400 mb-1">Encryption</label>
                        <select id="smtpEncryption" name="encryption" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                            <option value="none">None</option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                        </select>
                    </div>
                    <div>
                        <label for="smtpFromEmail" class="block text-sm font-medium text-slate-400 mb-1">From Email Address</label>
                        <input type="email" id="smtpFromEmail" name="from_email" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div class="md:col-span-2">
                        <label for="smtpFromName" class="block text-sm font-medium text-slate-400 mb-1">From Name (Optional)</label>
                        <input type="text" id="smtpFromName" name="from_name" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" id="saveSmtpBtn" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                </div>
            </form>
            <div id="smtpLoader" class="text-center py-4 hidden"><div class="loader mx-auto"></div></div>
        </div>

        <!-- Device Subscriptions Card -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Device Email Subscriptions</h2>
            <div class="mb-4">
                <label for="deviceSelect" class="block text-sm font-medium text-slate-400 mb-1">Select Device</label>
                <select id="deviceSelect" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    <option value="">-- Select a device --</option>
                </select>
            </div>

            <div id="subscriptionFormContainer" class="hidden border border-slate-700 rounded-lg p-4 mt-4">
                <h3 class="text-lg font-semibold text-white mb-3">Add/Edit Subscription for <span id="selectedDeviceName" class="text-cyan-400"></span></h3>
                <form id="deviceSubscriptionForm" class="space-y-3">
                    <input type="hidden" id="subscriptionId" name="id">
                    <input type="hidden" id="subscriptionDeviceId" name="device_id">
                    <div>
                        <label for="recipientEmail" class="block text-sm font-medium text-slate-400 mb-1">Recipient Email</label>
                        <input type="email" id="recipientEmail" name="recipient_email" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyOnline" name="notify_on_online" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Online</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyOffline" name="notify_on_offline" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Offline</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyWarning" name="notify_on_warning" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Warning</span>
                        </label>
                        <label class="flex items-center text-sm font-medium text-slate-400">
                            <input type="checkbox" id="notifyCritical" name="notify_on_critical" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500">
                            <span class="ml-2">Notify on Critical</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="cancelSubscriptionBtn" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600">Cancel</button>
                        <button type="submit" id="saveSubscriptionBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Save Subscription</button>
                    </div>
                </form>
            </div>

            <div id="subscriptionsList" class="mt-6">
                <h3 class="text-lg font-semibold text-white mb-3">Active Subscriptions</h3>
                <div id="subscriptionsTableBody" class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b border-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Recipient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Triggers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionsTable">
                            <!-- Subscriptions will be loaded here by JS -->
                        </tbody>
                    </table>
                </div>
                <div id="subscriptionsLoader" class="text-center py-8 hidden"><div class="loader mx-auto"></div></div>
                <div id="noSubscriptionsMessage" class="text-center py-8 hidden">
                    <i class="fas fa-bell-slash text-slate-600 text-4xl mb-4"></i>
                    <p class="text-slate-500">No subscriptions for this device yet.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>