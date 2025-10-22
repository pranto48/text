window.MapApp = window.MapApp || {};

MapApp.utils = {
    buildNodeTitle: (deviceData) => {
        let title = `${deviceData.name}<br>${deviceData.ip || 'No IP'}<br>Status: ${deviceData.status}`;
        if (deviceData.status === 'offline' && deviceData.last_ping_output) {
            const lines = deviceData.last_ping_output.split('\n');
            let reason = 'No response';
            for (const line of lines) {
                if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                    reason = line.trim();
                    break;
                }
            }
            const sanitizedReason = reason.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<br><small style="color: #fca5a5; font-family: monospace;">${sanitizedReason}</small>`;
        }
        return title;
    }
};