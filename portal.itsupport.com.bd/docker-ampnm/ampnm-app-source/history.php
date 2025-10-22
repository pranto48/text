<?php
require_once __DIR__ . '/includes/auth_check.php';
include __DIR__ . '/header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Ping History</h1>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
            <form id="historyFilterForm" class="flex flex-col sm:flex-row gap-4">
                <select name="host" id="hostSelector" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 text-white">
                    <option value="">All Hosts</option>
                </select>
                <button type="submit" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Filter</button>
                <a id="exportLink" href="export.php" class="px-6 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 text-center">Export CSV</a>
            </form>
        </div>

        <!-- Chart Container -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Performance Over Time</h2>
            <div id="chartLoader" class="text-center py-16"><div class="loader mx-auto"></div></div>
            <div class="h-80 hidden" id="chartContainer">
                <canvas id="historyChart"></canvas>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Detailed Log</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Host</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Packet Loss</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Avg Time</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <!-- Data will be inserted by JS -->
                    </tbody>
                </table>
                <div id="tableLoader" class="text-center py-8"><div class="loader mx-auto"></div></div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>