window.MapApp = window.MapApp || {};

MapApp.ui = {
    // DOM Elements
    els: {},

    // Cache DOM elements
    cacheElements: () => {
        MapApp.ui.els = {
            mapWrapper: document.getElementById('network-map-wrapper'),
            mapSelector: document.getElementById('mapSelector'),
            newMapBtn: document.getElementById('newMapBtn'),
            renameMapBtn: document.getElementById('renameMapBtn'),
            deleteMapBtn: document.getElementById('deleteMapBtn'),
            mapContainer: document.getElementById('map-container'),
            noMapsContainer: document.getElementById('no-maps'),
            createFirstMapBtn: document.getElementById('createFirstMapBtn'),
            currentMapName: document.getElementById('currentMapName'),
            scanNetworkBtn: document.getElementById('scanNetworkBtn'),
            refreshStatusBtn: document.getElementById('refreshStatusBtn'),
            liveRefreshToggle: document.getElementById('liveRefreshToggle'),
            addDeviceBtn: document.getElementById('addDeviceBtn'),
            addEdgeBtn: document.getElementById('addEdgeBtn'),
            fullscreenBtn: document.getElementById('fullscreenBtn'),
            exportBtn: document.getElementById('exportBtn'),
            importBtn: document.getElementById('importBtn'),
            importFile: document.getElementById('importFile'),
            deviceModal: document.getElementById('deviceModal'),
            deviceForm: document.getElementById('deviceForm'),
            cancelBtn: document.getElementById('cancelBtn'),
            edgeModal: document.getElementById('edgeModal'),
            edgeForm: document.getElementById('edgeForm'),
            cancelEdgeBtn: document.getElementById('cancelEdgeBtn'),
            scanModal: document.getElementById('scanModal'),
            closeScanModal: document.getElementById('closeScanModal'),
            scanForm: document.getElementById('scanForm'),
            scanLoader: document.getElementById('scanLoader'),
            scanResults: document.getElementById('scanResults'),
            scanInitialMessage: document.getElementById('scanInitialMessage')
        };
    },

    populateLegend: () => {
        const legendContainer = document.getElementById('status-legend');
        if (!legendContainer) return;
        const statusOrder = ['online', 'warning', 'critical', 'offline', 'unknown'];
        legendContainer.innerHTML = statusOrder.map(status => {
            const color = MapApp.config.statusColorMap[status];
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            return `<div class="legend-item"><div class="legend-dot" style="background-color: ${color};"></div><span>${label}</span></div>`;
        }).join('');
    },

    toggleDeviceModalFields: (type) => {
        const isAnnotation = type === 'box';
        const isPingable = !isAnnotation;
        document.getElementById('deviceIpWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('devicePortWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('pingIntervalWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('thresholdsWrapper').style.display = isPingable ? 'block' : 'none';
        document.getElementById('deviceIp').required = isPingable;
        document.getElementById('iconSizeLabel').textContent = isAnnotation ? 'Width' : 'Icon Size';
        document.getElementById('nameTextSizeLabel').textContent = isAnnotation ? 'Height' : 'Name Text Size';
    },

    openDeviceModal: (deviceId = null, prefill = {}) => {
        MapApp.ui.els.deviceForm.reset();
        document.getElementById('deviceId').value = '';
        const previewWrapper = document.getElementById('icon_preview_wrapper');
        previewWrapper.classList.add('hidden');

        if (deviceId) {
            const node = MapApp.state.nodes.get(deviceId);
            if (!node) {
                console.error("Node not found for ID:", deviceId);
                window.notyf.error("Device not found on map.");
                return;
            }
            document.getElementById('modalTitle').textContent = 'Edit Item';
            document.getElementById('deviceId').value = node.id;
            document.getElementById('deviceName').value = node.deviceData.name;
            document.getElementById('deviceIp').value = node.deviceData.ip;
            document.getElementById('checkPort').value = node.deviceData.check_port;
            document.getElementById('deviceType').value = node.deviceData.type;
            document.getElementById('icon_url').value = node.deviceData.icon_url || '';
            if (node.deviceData.icon_url) {
                document.getElementById('icon_preview').src = node.deviceData.icon_url;
                previewWrapper.classList.remove('hidden');
            }
            document.getElementById('pingInterval').value = node.deviceData.ping_interval;
            document.getElementById('iconSize').value = node.deviceData.icon_size;
            document.getElementById('nameTextSize').value = node.deviceData.name_text_size;
            document.getElementById('warning_latency_threshold').value = node.deviceData.warning_latency_threshold;
            document.getElementById('warning_packetloss_threshold').value = node.deviceData.warning_packetloss_threshold;
            document.getElementById('critical_latency_threshold').value = node.deviceData.critical_latency_threshold;
            document.getElementById('critical_packetloss_threshold').value = node.deviceData.critical_packetloss_threshold;
            document.getElementById('showLivePing').checked = node.deviceData.show_live_ping;
        } else {
            document.getElementById('modalTitle').textContent = 'Add Item';
            document.getElementById('deviceName').value = prefill.name || '';
            document.getElementById('deviceIp').value = prefill.ip || '';
        }
        MapApp.ui.toggleDeviceModalFields(document.getElementById('deviceType').value);
        // Use the shared openModal function
        openModal('deviceModal');
    },

    openEdgeModal: (edgeId) => {
        const edge = MapApp.state.edges.get(edgeId);
        if (!edge) {
            console.error("Edge not found for ID:", edgeId);
            window.notyf.error("Connection not found on map.");
            return;
        }
        document.getElementById('edgeId').value = edge.id;
        document.getElementById('connectionType').value = edge.connection_type || 'cat5';
        // Use the shared openModal function
        openModal('edgeModal');
    },

    updateAndAnimateEdges: () => {
        MapApp.state.tick++;
        const animatedDashes = [4 - (MapApp.state.tick % 12), 8, MapApp.state.tick % 12];
        const updates = [];
        const allEdges = MapApp.state.edges.get();
        if (MapApp.state.nodes.length > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(edge.from);
                const targetStatus = deviceStatusMap.get(edge.to);
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                const isActive = sourceStatus === 'online' && targetStatus === 'online';
                const color = isOffline ? MapApp.config.statusColorMap.offline : (MapApp.config.edgeColorMap[edge.connection_type] || MapApp.config.edgeColorMap.cat5);
                let dashes = false;
                if (isActive) { dashes = animatedDashes; } 
                else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio') { dashes = [5, 5]; }
                updates.push({ id: edge.id, color, dashes });
            });
        }
        if (updates.length > 0) MapApp.state.edges.update(updates);
        MapApp.state.animationFrameId = requestAnimationFrame(MapApp.ui.updateAndAnimateEdges);
    }
};