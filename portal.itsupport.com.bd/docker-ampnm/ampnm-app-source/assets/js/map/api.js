window.MapApp = window.MapApp || {};

MapApp.api = {
    get: async (action, params = {}) => {
        const res = await fetch(`${MapApp.config.API_URL}?action=${action}&${new URLSearchParams(params)}`);
        if (!res.ok) {
            const errorData = await res.json().catch(() => ({ error: 'Invalid JSON response from server' }));
            throw new Error(errorData.error || `HTTP error! status: ${res.status}`);
        }
        return res.json();
    },
    post: async (action, body) => {
        const res = await fetch(`${MapApp.config.API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (!res.ok) {
            const errorData = await res.json().catch(() => ({ error: 'Invalid JSON response from server' }));
            throw new Error(errorData.error || `HTTP error! status: ${res.status}`);
        }
        return res.json();
    }
};