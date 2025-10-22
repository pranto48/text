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
            scanInitialMessage: document.getElementById('scanInitialMessage'),

            // New elements for dynamic device form
            networkConfigWrapper: document.getElementById('networkConfigWrapper'),
            deviceIpWrapper: document.getElementById('deviceIpWrapper'),
            devicePortWrapper: document.getElementById('devicePortWrapper'),
            pingIntervalWrapper: document.getElementById('pingIntervalWrapper'),
            thresholdsWrapper: document.getElementById('thresholdsWrapper'),
            showLivePingWrapper: document.getElementById('showLivePingWrapper'),
            iconSizeLabel: document.getElementById('iconSizeLabel'),
            nameTextSizeLabel: document.getElementById('nameTextSizeLabel'),
            iconPreviewWrapper: document.getElementById('icon_preview_wrapper'),
            iconPreview: document.getElementById('icon_preview'),
            deviceTypeSelect: document.getElementById('deviceType'),
            deviceIpInput: document.getElementById('deviceIp'),
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
        const isBox = type === 'box';
        
        // Toggle Network Configuration section
        MapApp.ui.els.networkConfigWrapper.style.display = isBox ? 'none' : 'block';
        MapApp.ui.els.thresholdsWrapper.style.display = isBox ? 'none' : 'block';
        MapApp.ui.els.showLivePingWrapper.style.display = isBox ? 'none' : 'block';

        // Set required attribute for IP based on type
        MapApp.ui.els.deviceIpInput.required = !isBox;

        // Update labels for icon/size fields
        MapApp.ui.els.iconSizeLabel.textContent = isBox ? 'Width' : 'Icon Size';
        MapApp.ui.els.nameTextSizeLabel.textContent = isBox ? 'Height' : 'Name Text Size';
    },

    openDeviceModal: async (deviceId = null, prefill = {}) => {
        MapApp.ui.els.deviceForm.reset();
        document.getElementById('deviceId').value = '';
        MapApp.ui.els.iconPreviewWrapper.classList.add('hidden');
        MapApp.ui.els.deviceIpInput.setCustomValidity(''); // Clear custom validation message

        // Populate map selector
        const mapSelector = document.getElementById('deviceMap');
        if (mapSelector) {
            const maps = await MapApp.api.get('get_maps');
            mapSelector.innerHTML = '<option value="">Unassigned</option>' + 
                maps.map(map => `<option value="${map.id}">${map.name}</option>`).join('');
        }

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
            document.getElementById('deviceIp').value = node.deviceData.ip || '';
            document.getElementById('checkPort').value = node.deviceData.check_port || '';
            MapApp.ui.els.deviceTypeSelect.value = node.deviceData.type;
            document.getElementById('icon_url').value = node.deviceData.icon_url || '';
            if (node.deviceData.icon_url) {
                MapApp.ui.els.iconPreview.src = node.deviceData.icon_url;
                MapApp.ui.els.iconPreviewWrapper.classList.remove('hidden');
            }
            document.getElementById('pingInterval').value = node.deviceData.ping_interval || '';
            document.getElementById('iconSize').value = node.deviceData.icon_size || 50;
            document.getElementById('nameTextSize').value = node.deviceData.name_text_size || 14;
            document.getElementById('warning_latency_threshold').value = node.deviceData.warning_latency_threshold || '';
            document.getElementById('warning_packetloss_threshold').value = node.deviceData.warning_packetloss_threshold || '';
            document.getElementById('critical_latency_threshold').value = node.deviceData.critical_latency_threshold || '';
            document.getElementById('critical_packetloss_threshold').value = node.deviceData.critical_packetloss_threshold || '';
            document.getElementById('showLivePing').checked = node.deviceData.show_live_ping;
            if (mapSelector && node.deviceData.map_id) {
                mapSelector.value = node.deviceData.map_id;
            }
        } else {
            document.getElementById('modalTitle').textContent = 'Add Item';
            document.getElementById('deviceName').value = prefill.name || '';
            document.getElementById('deviceIp').value = prefill.ip || '';
            MapApp.ui.els.deviceTypeSelect.value = 'server'; // Default to server for new devices
            document.getElementById('iconSize').value = 50;
            document.getElementById('nameTextSize').value = 14;
            document.getElementById('showLivePing').checked = false;
        }
        MapApp.ui.toggleDeviceModalFields(MapApp.ui.els.deviceTypeSelect.value);
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