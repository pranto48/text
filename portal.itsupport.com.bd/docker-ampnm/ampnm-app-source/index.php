<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
            <div id="map-selector-container" class="flex items-center gap-2">
                <!-- Populated by JS -->
            </div>
        </div>

        <div id="dashboard-content">
            <div class="text-center py-16" id="dashboardLoader"><div class="loader mx-auto"></div></div>
            <div id="dashboard-widgets" class="hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Status Chart -->
                    <div class="lg:col-span-1 bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 flex flex-col items-center justify-center">
                        <h3 class="text-lg font-semibold text-white mb-4">Device Status Overview</h3>
                        <div class="w-48 h-48 relative">
                            <canvas id="statusChart"></canvas>
                            <div id="totalDevicesText" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                                <span class="text-4xl font-bold">--</span>
                                <span class="text-sm text-slate-400">Total Devices</span>
                            </div>
                        </div>
                    </div>
                    <!-- Status Counters -->
                    <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Online</h3>
                            <div id="onlineCount" class="text-4xl font-bold text-green-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Warning</h3>
                            <div id="warningCount" class="text-4xl font-bold text-yellow-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Critical</h3>
                            <div id="criticalCount" class="text-4xl font-bold text-red-400 mt-2">--</div>
                        </div>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-lg p-6 text-center">
                            <h3 class="text-sm font-medium text-slate-400">Offline</h3>
                            <div id="offlineCount" class="text-4xl font-bold text-slate-500 mt-2">--</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Ping Test -->
                    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                        <h2 class="text-xl font-semibold text-white mb-4">Manual Ping Test</h2>
                        <form id="pingForm" class="flex flex-col sm:flex-row gap-4 mb-4">
                            <input type="text" id="pingHostInput" name="ping_host" placeholder="Enter hostname or IP" value="192.168.1.1" class="flex-1 px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white">
                            <button type="submit" id="pingButton" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500">
                                <i class="fas fa-bolt mr-2"></i>Ping
                            </button>
                        </form>
                        <div id="pingResultContainer" class="hidden mt-4">
                            <pre id="pingResultPre" class="bg-slate-900/50 text-white text-sm p-4 rounded-lg overflow-x-auto"></pre>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
                        <h2 class="text-xl font-semibold text-white mb-4">Recent Activity</h2>
                        <div id="recentActivityList" class="space-y-3 max-h-60 overflow-y-auto">
                            <!-- Recent activity items will be loaded here by JS -->
                        </div>
                        <div id="noRecentActivityMessage" class="text-center py-4 text-slate-500 hidden">
                            <i class="fas fa-bell text-4xl mb-2"></i>
                            <p>No recent activity for this map.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>