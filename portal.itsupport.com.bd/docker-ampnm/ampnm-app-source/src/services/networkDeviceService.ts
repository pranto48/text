const PHP_API_URL = '/api.php'; // Changed to relative path

export interface NetworkDevice {
  id?: string;
  user_id?: string; // This will be handled by the PHP backend session
  name: string;
  ip_address: string;
  position_x: number;
  position_y: number;
  icon: string;
  status?: 'online' | 'offline' | 'unknown';
  ping_interval?: number;
  icon_size?: number;
  name_text_size?: number;
  last_ping?: string | null;
  last_ping_result?: boolean | null;
  // Additional fields from PHP backend
  check_port?: number | null;
  type?: string; // e.g., 'server', 'router', 'box'
  description?: string | null;
  map_id?: string | null; // PHP uses INT, but ReactFlow uses string IDs
  warning_latency_threshold?: number | null;
  warning_packetloss_threshold?: number | null;
  critical_latency_threshold?: number | null;
  critical_packetloss_threshold?: number | null;
  last_avg_time?: number | null;
  last_ttl?: number | null;
  show_live_ping?: boolean;
  map_name?: string; // For display purposes
  last_ping_output?: string; // For display purposes
}

export interface NetworkEdge {
  id: string;
  source: string;
  target: string;
  connection_type: string;
}

export interface MapData {
  devices: Omit<NetworkDevice, 'user_id' | 'status' | 'last_ping' | 'last_ping_result' | 'map_name' | 'last_ping_output'>[];
  edges: { source: string; target: string; connection_type: string }[];
}

export interface LicenseStatus {
  app_license_key?: string; // NEW: Current application license key
  can_add_device: boolean;
  max_devices: number;
  license_message: string;
  license_status_code: 'active' | 'expired' | 'grace_period' | 'disabled' | 'error' | 'unknown' | 'in_use'; // Added 'in_use'
  license_grace_period_end: number | null; // Unix timestamp
  installation_id?: string; // NEW: Installation ID
}

// Define a type for Map data from PHP backend
export interface Map {
  id: string;
  name: string;
}

export interface DashboardStats {
  total: number;
  online: number;
  warning: number;
  critical: number;
  offline: number;
}

export interface RecentActivity {
  created_at: string;
  status: string;
  details: string;
  device_name: string;
  device_ip: string;
}

export interface FullDashboardData {
  stats: DashboardStats;
  devices: NetworkDevice[];
  recent_activity: RecentActivity[];
}

// NEW: User interface
export interface User {
  id: string;
  username: string;
  role: 'admin' | 'user';
  created_at: string;
}

const callPhpApi = async (action: string, method: 'GET' | 'POST', params?: Record<string, any>, body?: any) => {
  const options: RequestInit = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const queryString = params ? `&${new URLSearchParams(params).toString()}` : '';
  const response = await fetch(`${PHP_API_URL}?action=${action}${queryString}`, options);
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
  }
  return response.json();
};

export const getMaps = async (): Promise<Map[]> => {
  const data = await callPhpApi('get_maps', 'GET');
  return data.map((m: any) => ({ id: String(m.id), name: m.name }));
};

export const getDevices = async (map_id?: string | null) => {
  const params = map_id ? { map_id } : {};
  const data = await callPhpApi('get_devices', 'GET', params);
  // Map PHP backend data to frontend interface
  return data.map((d: any) => ({
    id: String(d.id), // Ensure ID is string for ReactFlow
    name: d.name,
    ip_address: d.ip,
    position_x: parseFloat(d.x) || 0,
    position_y: parseFloat(d.y) || 0,
    icon: d.type, // Using 'type' as icon for now, can be refined
    status: d.status,
    ping_interval: d.ping_interval,
    icon_size: d.icon_size,
    name_text_size: d.name_text_size,
    last_ping: d.last_seen, // PHP uses last_seen for last ping time
    last_ping_result: d.status === 'online', // Infer from status
    check_port: d.check_port,
    type: d.type,
    description: d.description,
    map_id: String(d.map_id),
    warning_latency_threshold: d.warning_latency_threshold,
    warning_packetloss_threshold: d.warning_packetloss_threshold,
    critical_latency_threshold: d.critical_latency_threshold,
    critical_packetloss_threshold: d.critical_packetloss_threshold,
    last_avg_time: d.last_avg_time,
    last_ttl: d.last_ttl,
    show_live_ping: Boolean(parseInt(d.show_live_ping)),
    map_name: d.map_name,
    last_ping_output: d.last_ping_output,
  })) as NetworkDevice[];
};

export const getUnmappedDevices = async (): Promise<NetworkDevice[]> => {
  const data = await callPhpApi('get_devices', 'GET', { unmapped: true });
  return data.map((d: any) => ({
    id: String(d.id),
    name: d.name,
    ip_address: d.ip,
    position_x: parseFloat(d.x) || 0,
    position_y: parseFloat(d.y) || 0,
    icon: d.type,
    status: d.status,
    ping_interval: d.ping_interval,
    icon_size: d.icon_size,
    name_text_size: d.name_text_size,
    last_ping: d.last_seen,
    last_ping_result: d.status === 'online',
    check_port: d.check_port,
    type: d.type,
    description: d.description,
    map_id: null,
    warning_latency_threshold: d.warning_latency_threshold,
    warning_packetloss_threshold: d.warning_packetloss_threshold,
    critical_latency_threshold: d.critical_latency_threshold,
    critical_packetloss_threshold: d.critical_packetloss_threshold,
    last_avg_time: d.last_avg_time,
    last_ttl: d.last_ttl,
    show_live_ping: Boolean(parseInt(d.show_live_ping)),
    map_name: d.map_name,
    last_ping_output: d.last_ping_output,
  })) as NetworkDevice[];
};

export const getFullDashboardData = async (mapId: string): Promise<FullDashboardData> => {
  const data = await callPhpApi('get_dashboard_data', 'GET', { map_id: mapId });
  return {
    stats: data.stats,
    devices: data.devices.map((d: any) => ({
      id: String(d.id),
      name: d.name,
      ip_address: d.ip,
      status: d.status,
      type: d.type,
      map_name: d.map_name,
      last_ping: d.last_seen,
      // Minimal fields for dashboard list, full details fetched by getDevices
    })),
    recent_activity: data.recent_activity,
  };
};

export const addDevice = async (device: Omit<NetworkDevice, 'id' | 'user_id' | 'status' | 'last_ping' | 'last_ping_result' | 'map_name' | 'last_ping_output'>) => {
  const payload = {
    name: device.name,
    ip: device.ip_address,
    position_x: device.position_x,
    position_y: device.position_y,
    type: device.icon, // Using icon as type for PHP backend
    ping_interval: device.ping_interval,
    icon_size: device.icon_size,
    name_text_size: device.name_text_size,
    check_port: device.check_port,
    description: device.description,
    map_id: device.map_id,
    warning_latency_threshold: device.warning_latency_threshold,
    warning_packetloss_threshold: device.warning_packetloss_threshold,
    critical_latency_threshold: device.critical_latency_threshold,
    critical_packetloss_threshold: device.critical_packetloss_threshold,
    show_live_ping: device.show_live_ping,
  };
  const data = await callPhpApi('create_device', 'POST', undefined, payload);
  return { ...data, id: String(data.id) } as NetworkDevice;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>) => {
  const payload: { [key: string]: any } = { id };
  payload.updates = {
    name: updates.name,
    ip: updates.ip_address,
    x: updates.position_x,
    y: updates.position_y,
    type: updates.icon, // Using icon as type for PHP backend
    ping_interval: updates.ping_interval,
    icon_size: updates.icon_size,
    name_text_size: updates.name_text_size,
    check_port: updates.check_port,
    description: updates.description,
    map_id: updates.map_id,
    warning_latency_threshold: updates.warning_latency_threshold,
    warning_packetloss_threshold: updates.warning_packetloss_threshold,
    critical_latency_threshold: updates.critical_latency_threshold,
    critical_packetloss_threshold: updates.critical_latency_threshold,
    show_live_ping: updates.show_live_ping,
    status: updates.status,
    last_seen: updates.last_ping,
    last_avg_time: updates.last_avg_time,
    last_ttl: updates.last_ttl,
  };
  // Filter out undefined values from updates
  for (const key in payload.updates) {
    if (payload.updates[key] === undefined) {
      delete payload.updates[key];
    }
  }
  const data = await callPhpApi('update_device', 'POST', undefined, payload);
  return { ...data, id: String(data.id) } as NetworkDevice;
};

export const updateDeviceStatusByIp = async (ip_address: string, status: 'online' | 'offline') => {
  // This function is not directly supported by the PHP API as a standalone action.
  // The PHP API's 'check_device' or 'ping_all_devices' handles status updates.
  // For now, we'll simulate this by calling 'manual_ping' and letting the PHP backend update.
  // In a more integrated system, you might have a specific API endpoint for this.
  console.warn("updateDeviceStatusByIp is a client-side simulation. PHP backend handles actual status updates via ping actions.");
  // We can't directly update by IP from the client without a specific PHP endpoint.
  // The `performServerPing` already saves results and updates device status.
  // So, this function might become redundant or need a dedicated PHP endpoint.
  // For now, we'll just return a dummy success.
  return { success: true, message: "Status update handled by server ping logic." };
};

export const deleteDevice = async (id: string) => {
  await callPhpApi('delete_device', 'POST', undefined, { id });
};

export const getEdges = async (map_id?: string | null) => {
  const data = await callPhpApi('get_edges', 'GET', { map_id });
  return data.map((e: any) => ({
    id: String(e.id),
    source: String(e.source_id),
    target: String(e.target_id),
    connection_type: e.connection_type,
  })) as NetworkEdge[];
};

export const addEdgeToDB = async (edge: { source: string; target: string; map_id: string }) => {
  const payload = {
    source_id: edge.source,
    target_id: edge.target,
    map_id: edge.map_id,
    connection_type: 'cat5', // Default connection type
  };
  const data = await callPhpApi('create_edge', 'POST', undefined, payload);
  return { ...data, id: String(data.id) } as NetworkEdge;
};

export const updateEdgeInDB = async (id: string, updates: { connection_type: string }) => {
  const payload = { id, connection_type: updates.connection_type };
  const data = await callPhpApi('update_edge', 'POST', undefined, payload);
  return { ...data, id: String(data.id) } as NetworkEdge;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  await callPhpApi('delete_edge', 'POST', undefined, { id: edgeId });
};

export const importMap = async (mapData: MapData, map_id: string) => {
  const payload = {
    map_id,
    devices: mapData.devices.map(d => ({
      ...d,
      ip: d.ip_address, // Map ip_address to ip for PHP backend
      type: d.icon, // Map icon to type for PHP backend
      x: d.position_x,
      y: d.position_y,
      show_live_ping: d.show_live_ping ? 1 : 0, // Convert boolean to int
    })),
    edges: mapData.edges.map(e => ({
      from: e.source,
      to: e.target,
      connection_type: e.connection_type,
    })),
  };
  await callPhpApi('import_map', 'POST', undefined, payload);
};

export const getLicenseStatus = async (): Promise<LicenseStatus> => {
  const status = await callPhpApi('get_license_status', 'GET');
  // Convert grace_period_end to Unix timestamp if it exists
  if (status.license_grace_period_end) {
    status.license_grace_period_end = new Date(status.license_grace_period_end).getTime() / 1000;
  }
  return status;
};

export const forceLicenseRecheck = async (): Promise<{ success: boolean; message: string; license_status_code: LicenseStatus['license_status_code']; license_message: string }> => {
  return await callPhpApi('force_license_recheck', 'POST');
};

export const updateAppLicenseKey = async (newLicenseKey: string): Promise<{ success: boolean; message: string; license_status_code: LicenseStatus['license_status_code']; license_message: string; app_license_key: string }> => {
  return await callPhpApi('update_app_license_key', 'POST', undefined, { new_license_key: newLicenseKey });
};

// NEW: User Management API calls
export const getUsers = async (): Promise<User[]> => {
  const data = await callPhpApi('get_users', 'GET');
  return data.map((u: any) => ({
    id: String(u.id),
    username: u.username,
    role: u.role,
    created_at: u.created_at,
  })) as User[];
};

export const createUser = async (username: string, password: string, role: User['role']) => {
  return await callPhpApi('create_user', 'POST', undefined, { username, password, role });
};

export const updateUserRole = async (id: string, role: User['role']) => {
  return await callPhpApi('update_user_role', 'POST', undefined, { id, role });
};

export const deleteUser = async (id: string) => {
  return await callPhpApi('delete_user', 'POST', undefined, { id });
};

// NEW: Docker Maintenance API call
export const updateDockerImage = async (imageName: string): Promise<{ success: boolean; message: string; error?: string }> => {
  return await callPhpApi('docker_update', 'POST', undefined, { image_name: imageName });
};

// Real-time subscription for device changes - NOT USED WITH PHP BACKEND
// This function is now a no-op or can be removed if not needed for other purposes.
export const subscribeToDeviceChanges = (callback: (payload: any) => void) => {
  console.warn("subscribeToDeviceChanges is not actively supported when using PHP backend for device/edge management. Polling or a custom WebSocket solution would be needed for real-time updates.");
  // Return a dummy object that mimics a channel for compatibility if needed,
  // or simply remove calls to this function from components.
  return {
    on: () => ({ on: () => ({ on: () => ({ subscribe: () => {} }) }) }),
    subscribe: () => {},
    removeChannel: () => {},
  };
};