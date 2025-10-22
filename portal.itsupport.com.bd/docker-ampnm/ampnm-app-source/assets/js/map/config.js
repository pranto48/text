window.MapApp = window.MapApp || {};

MapApp.config = {
    API_URL: 'api.php',
    REFRESH_INTERVAL_SECONDS: 30,
    iconMap: {
        server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
        camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d',
        punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
        rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
        cloud: '\uf0c2', database: '\uf1c0', box: '\uf49e'
    },
    statusColorMap: {
        online: '#22c55e', warning: '#f59e0b', critical: '#ef4444',
        offline: '#64748b', unknown: '#94a3b8'
    },
    edgeColorMap: {
        cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8', radio: '#84cc16'
    }
};