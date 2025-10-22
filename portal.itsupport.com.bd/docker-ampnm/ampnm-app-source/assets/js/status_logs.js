function initStatusLogs() {
    const API_URL = 'api.php';
    let statusLogChart = null;
    let liveInterval = null;

    const els = {
        mapSelector: document.getElementById('mapSelector'),
        deviceSelector: document.getElementById('deviceSelector'),
        periodSelector: document.getElementById('periodSelector'),
        chartTitle: document.getElementById('chartTitle'),
        chartLoader: document.getElementById('chartLoader'),
        chartContainer: document.getElementById('chartContainer'),
        statusLogChartCanvas: document.getElementById('statusLogChart'),
        noDataMessage: document.getElementById('noDataMessage'),
    };

    const state = {
        currentMapId: null,
        currentDeviceId: '',
        currentPeriod: '24h',
    };

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json())
    };

    const populateMapSelector = async () => {
        const maps = await api.get('get_maps');
        if (maps.length > 0) {
            els.mapSelector.innerHTML = maps.map(map => `<option value="${map.id}">${map.name}</option>`).join('');
            state.currentMapId = maps[0].id;
        } else {
            els.mapSelector.innerHTML = '<option>No maps found</option>';
        }
    };

    const populateDeviceSelector = async () => {
        if (!state.currentMapId) return;
        const devices = await api.get('get_devices', { map_id: state.currentMapId });
        els.deviceSelector.innerHTML = '<option value="">All Devices</option>' + 
            devices.map(d => `<option value="${d.id}">${d.name} (${d.ip || 'No IP'})</option>`).join('');
    };

    const loadChartData = async () => {
        if (liveInterval) clearInterval(liveInterval);
        els.chartLoader.classList.remove('hidden');
        els.chartContainer.classList.add('hidden');
        els.noDataMessage.classList.add('hidden');
        if (statusLogChart) statusLogChart.destroy();

        const data = await api.get('get_status_logs', {
            map_id: state.currentMapId,
            device_id: state.currentDeviceId,
            period: state.currentPeriod
        });

        els.chartLoader.classList.add('hidden');

        if (data.length === 0) {
            els.noDataMessage.classList.remove('hidden');
            return;
        }

        els.chartContainer.classList.remove('hidden');

        const labels = data.map(d => d.time_group);
        const datasets = [
            { label: 'Critical', data: data.map(d => d.critical_count), backgroundColor: '#ef4444' },
            { label: 'Warning', data: data.map(d => d.warning_count), backgroundColor: '#f59e0b' },
            { label: 'Offline', data: data.map(d => d.offline_count), backgroundColor: '#64748b' },
        ];

        statusLogChart = new Chart(els.statusLogChartCanvas, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1' } } },
                scales: {
                    x: { type: 'time', time: { unit: state.currentPeriod === '24h' || state.currentPeriod === 'live' ? 'hour' : 'day' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: '#334155' } }
                }
            }
        });

        if (state.currentPeriod === 'live') {
            liveInterval = setInterval(loadChartData, 30000); // Refresh every 30 seconds
        }
    };

    const updateFilters = () => {
        state.currentMapId = els.mapSelector.value;
        state.currentDeviceId = els.deviceSelector.value;
        loadChartData();
    };

    els.mapSelector.addEventListener('change', async () => {
        state.currentMapId = els.mapSelector.value;
        await populateDeviceSelector();
        state.currentDeviceId = ''; // Reset device filter
        loadChartData();
    });

    els.deviceSelector.addEventListener('change', updateFilters);

    els.periodSelector.addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON') {
            state.currentPeriod = e.target.dataset.period;
            
            // Update button styles
            els.periodSelector.querySelectorAll('button').forEach(btn => {
                btn.classList.remove('bg-slate-700', 'text-white');
            });
            e.target.classList.add('bg-slate-700', 'text-white');

            // Update title
            els.chartTitle.textContent = `Status Events in the Last ${e.target.textContent}`;
            if (state.currentPeriod === 'live') {
                els.chartTitle.textContent = 'Live Status Events (Last 1 Hour)';
            }

            loadChartData();
        }
    });

    // Initial Load
    (async () => {
        await populateMapSelector();
        await populateDeviceSelector();
        await loadChartData();
    })();
}