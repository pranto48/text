function initHistory() {
    const API_URL = 'api.php';
    const historyChartCanvas = document.getElementById('historyChart');
    const historyTableBody = document.getElementById('historyTableBody');
    const chartLoader = document.getElementById('chartLoader');
    const tableLoader = document.getElementById('tableLoader');
    const chartContainer = document.getElementById('chartContainer');
    const filterForm = document.getElementById('historyFilterForm');
    const hostSelector = document.getElementById('hostSelector');
    const exportLink = document.getElementById('exportLink');

    let historyChart = null;

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json())
    };

    const loadHistoryData = async (host) => {
        chartLoader.classList.remove('hidden');
        tableLoader.classList.remove('hidden');
        chartContainer.classList.add('hidden');
        historyTableBody.innerHTML = '';

        try {
            const historyData = await api.get('get_ping_history', { host: host, limit: 100 });
            
            const reversedData = [...historyData].reverse();
            historyTableBody.innerHTML = reversedData.map(item => {
                const statusClass = item.success ? 'text-green-400' : 'text-red-400';
                const statusText = item.success ? 'Success' : 'Failed';
                return `
                    <tr class="border-b border-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-white">${item.host}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400">${new Date(item.created_at).toLocaleString()}</td>
                        <td class="px-6 py-4 whitespace-nowrap ${statusClass}">${statusText}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-300">${item.packet_loss}%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-300">${item.avg_time} ms</td>
                    </tr>
                `;
            }).join('');

            if (historyChart) historyChart.destroy();
            
            historyChart = new Chart(historyChartCanvas, {
                type: 'line',
                data: {
                    labels: historyData.map(h => new Date(h.created_at).toLocaleTimeString()),
                    datasets: [
                        {
                            label: 'Avg Time (ms)',
                            data: historyData.map(h => h.avg_time),
                            borderColor: '#22d3ee',
                            yAxisID: 'y',
                        },
                        {
                            label: 'Packet Loss (%)',
                            data: historyData.map(h => h.packet_loss),
                            borderColor: '#f43f5e',
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Avg Time (ms)', color: '#22d3ee' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                        y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Packet Loss (%)', color: '#f43f5e' }, ticks: { color: '#94a3b8' }, grid: { drawOnChartArea: false } },
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
                    },
                    plugins: { legend: { labels: { color: '#cbd5e1' } } }
                }
            });

            chartContainer.classList.remove('hidden');

        } catch (error) {
            console.error('Failed to load history:', error);
        } finally {
            chartLoader.classList.add('hidden');
            tableLoader.classList.add('hidden');
        }
    };

    const populateHostSelector = async () => {
        const devices = await api.get('get_devices');
        const hosts = [...new Set(devices.filter(d => d.ip).map(d => d.ip))].sort();
        hostSelector.innerHTML = '<option value="">All Hosts</option>' + hosts.map(h => `<option value="${h}">${h}</option>`).join('');
    };

    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const selectedHost = hostSelector.value;
        exportLink.href = `export.php?host=${encodeURIComponent(selectedHost)}`;
        loadHistoryData(selectedHost);
    });

    populateHostSelector().then(() => {
        const initialHost = new URLSearchParams(window.location.search).get('host') || '';
        if(initialHost) hostSelector.value = initialHost;
        loadHistoryData(initialHost);
    });
}