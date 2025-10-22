window.MapApp = window.MapApp || {};

MapApp.state = {
    network: null,
    nodes: new vis.DataSet([]),
    edges: new vis.DataSet([]),
    maps: [],
    currentMapId: null,
    pingIntervals: {},
    animationFrameId: null,
    tick: 0,
    globalRefreshIntervalId: null
};