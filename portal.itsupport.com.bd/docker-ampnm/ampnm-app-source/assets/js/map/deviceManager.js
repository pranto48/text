window.MapApp = window.MapApp || {};

MapApp.deviceManager = {
    pingSingleDevice: async (deviceId) => {
        const node = MapApp.state.nodes.get(deviceId);
        if (!node || node.deviceData.type === 'box') return;
        
        const oldStatus = node.deviceData.status;
        MapApp.state.nodes.update({ id: deviceId, icon: { ...node.icon, color: '#06b6d4' } });
        const result = await MapApp.api.post('check_device', { id: deviceId });
        const newStatus = result.status;

        if (newStatus !== oldStatus) {
            if (newStatus === 'warning') {
                SoundManager.play('warning');
            } else if (newStatus === 'critical') {
                SoundManager.play('critical');
            } else if (newStatus === 'offline') {
                SoundManager.play('offline');
            } else if (newStatus === 'online' && (oldStatus === 'offline' || oldStatus === 'critical' || oldStatus === 'warning')) {
                SoundManager.play('online');
            }

            if (newStatus === 'critical' || newStatus === 'offline') {
                window.notyf.error({ message: `Device '${node.deviceData.name}' is now ${newStatus}.`, duration: 5000, dismissible: true });
            } else if (newStatus === 'online' && (oldStatus === 'critical' || oldStatus === 'offline')) {
                window.notyf.success({ message: `Device '${node.deviceData.name}' is back online.`, duration: 5000 });
            }
        }

        const updatedDeviceData = { ...node.deviceData, status: newStatus, last_avg_time: result.last_avg_time, last_ttl: result.last_ttl, last_ping_output: result.last_ping_output };
        let label = updatedDeviceData.name;
        if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
            label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
        }
        MapApp.state.nodes.update({ id: deviceId, deviceData: updatedDeviceData, icon: { ...node.icon, color: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown }, title: MapApp.utils.buildNodeTitle(updatedDeviceData), label: label });
    },

    performBulkRefresh: async () => {
        const icon = MapApp.ui.els.refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        
        try {
            const result = await MapApp.api.post('ping_all_devices', { map_id: MapApp.state.currentMapId });
            if (!result.success || !result.updated_devices) {
                throw new Error('Invalid response from server during bulk refresh.');
            }

            let statusChanges = 0;
            const nodeUpdates = result.updated_devices.map(device => {
                const node = MapApp.state.nodes.get(device.id);
                if (!node) return null;

                if (device.old_status !== device.status) {
                    statusChanges++;
                    if (device.status === 'warning') {
                        SoundManager.play('warning');
                    } else if (device.status === 'critical') {
                        SoundManager.play('critical');
                    } else if (device.status === 'offline') {
                        SoundManager.play('offline');
                    } else if (device.status === 'online' && (device.old_status === 'offline' || device.old_status === 'critical' || device.old_status === 'warning')) {
                        SoundManager.play('online');
                    }
                    
                    if (device.status === 'critical' || device.status === 'offline') {
                        window.notyf.error({ message: `Device '${device.name}' is now ${device.status}.`, duration: 5000, dismissible: true });
                    } else if (device.status === 'online' && (device.old_status === 'critical' || device.old_status === 'offline')) {
                        window.notyf.success({ message: `Device '${device.name}' is back online.`, duration: 5000 });
                    } else {
                        window.notyf.open({ type: 'info', message: `Device '${device.name}' changed status to ${device.status}.`, duration: 5000 });
                    }
                }

                const updatedDeviceData = { ...node.deviceData, ...device };
                let label = updatedDeviceData.name;
                if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                    label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
                }
                
                return {
                    id: device.id,
                    deviceData: updatedDeviceData,
                    icon: { ...node.icon, color: MapApp.config.statusColorMap[device.status] || MapApp.config.statusColorMap.unknown },
                    title: MapApp.utils.buildNodeTitle(updatedDeviceData),
                    label: label
                };
            }).filter(Boolean);

            if (nodeUpdates.length > 0) {
                MapApp.state.nodes.update(nodeUpdates);
            }

            if (statusChanges === 0 && result.updated_devices.length > 0) {
                window.notyf.success({ message: 'All device statuses are stable.', duration: 2000 });
            }

            return result.updated_devices.length;

        } catch (error) {
            console.error("An error occurred during the bulk refresh process:", error);
            window.notyf.error("Failed to refresh device statuses.");
            return 0;
        } finally {
            icon.classList.remove('fa-spin');
        }
    },

    setupAutoPing: (devices) => {
        Object.values(MapApp.state.pingIntervals).forEach(clearInterval);
        MapApp.state.pingIntervals = {};
        devices.forEach(device => {
            if (device.ping_interval > 0 && device.ip) {
                MapApp.state.pingIntervals[device.id] = setInterval(() => MapApp.deviceManager.pingSingleDevice(device.id), device.ping_interval * 1000);
            }
        });
    }
};