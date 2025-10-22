<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">Status Event Logs</h1>

        <!-- Filters -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="mapSelector" class="block text-sm font-medium text-slate-400 mb-1">Map</label>
                    <select id="mapSelector" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white"></select>
                </div>
                <div>
                    <label for="deviceSelector" class="block text-sm font-medium text-slate-400 mb-1">Device</label>
                    <select id="deviceSelector" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                        <option value="">All Devices</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Time Period</label>
                    <div id="periodSelector" class="flex rounded-lg bg-slate-900 border border-slate-600 p-1">
                        <button data-period="live" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">Live</button>
                        <button data-period="24h" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700 bg-slate-700 text-white">24 Hours</button>
                        <button data-period="7d" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">7 Days</button>
                        <button data-period="30d" class="flex-1 px-3 py-1 text-sm rounded-md text-slate-300 hover:bg-slate-700">30 Days</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 id="chartTitle" class="text-xl font-semibold text-white mb-4">Status Events in the Last 24 Hours</h2>
            <div id="chartLoader" class="text-center py-16"><div class="loader mx-auto"></div></div>
            <div class="h-96 hidden" id="chartContainer">
                <canvas id="statusLogChart"></canvas>
            </div>
            <div id="noDataMessage" class="text-center py-16 hidden">
                <i class="fas fa-chart-bar text-slate-600 text-4xl mb-4"></i>
                <p class="text-slate-500">No status event data found for the selected period.</p>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>