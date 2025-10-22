import { useState, useEffect, useCallback, useMemo } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Wifi, Server, Clock, RefreshCw, Network, Key, Users } from "lucide-react"; // Added Users icon
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import {
  getLicenseStatus,
  LicenseStatus,
  User, // Import User interface
} from "@/services/networkDeviceService";
import { Skeleton } from "@/components/ui/skeleton";
import DashboardContent from "@/components/DashboardContent";
import { useDashboardData } from "@/hooks/useDashboardData";
import LicenseManager from "@/components/LicenseManager";
import UserManagement from "@/components/UserManagement"; // Import the new UserManagement component

const MainApp = () => {
  const {
    maps,
    currentMapId,
    setCurrentMapId,
    devices,
    dashboardStats,
    recentActivity,
    isLoading,
    fetchMaps,
    fetchDashboardData,
  } = useDashboardData();

  const [licenseStatus, setLicenseStatus] = useState<LicenseStatus>({ app_license_key: '', can_add_device: false, max_devices: 0, license_message: 'Loading license status...', license_status_code: 'unknown', license_grace_period_end: null, installation_id: '' });
  const [userRole, setUserRole] = useState<User['role']>('user'); // State for user role

  const fetchLicenseStatus = useCallback(async () => {
    try {
      const status = await getLicenseStatus();
      setLicenseStatus(status);
    } catch (error) {
      console.error("Failed to load license status:", error);
      setLicenseStatus({ app_license_key: '', can_add_device: false, max_devices: 0, license_message: 'Error loading license status.', license_status_code: 'error', license_grace_period_end: null, installation_id: '' });
    }
  }, []);

  // Fetch user role from PHP session (this would typically be done on initial load or via a dedicated API endpoint)
  // For now, we'll simulate fetching it from a global variable or a simple API call if available.
  // In a real React app, you'd have an auth context providing this.
  useEffect(() => {
    // This is a placeholder. In a real app, you'd fetch this from your backend.
    // For example, if your PHP backend exposes a /api.php?action=get_user_info endpoint
    // that returns { role: 'admin' | 'user' }.
    const fetchUserRole = async () => {
      try {
        const response = await fetch('/api.php?action=get_user_info'); // Assuming this endpoint exists
        if (response.ok) {
          const data = await response.json();
          setUserRole(data.role);
        } else {
          // Fallback to 'user' if not logged in or endpoint not found
          setUserRole('user');
        }
      } catch (error) {
        console.error("Failed to fetch user role:", error);
        setUserRole('user'); // Default to 'user' on error
      }
    };
    fetchUserRole();
  }, []);


  useEffect(() => {
    fetchLicenseStatus();
  }, [fetchLicenseStatus]);

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto p-4">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Network className="h-8 w-8 text-primary" />
            <h1 className="text-3xl font-bold">Local Network Monitor</h1>
          </div>
        </div>

        <Tabs defaultValue="dashboard" className="w-full">
          <TabsList className="mb-4">
            <TabsTrigger value="dashboard" className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Dashboard
            </TabsTrigger>
            <TabsTrigger value="devices" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Devices
            </TabsTrigger>
            <TabsTrigger value="ping" className="flex items-center gap-2">
              <Wifi className="h-4 w-4" />
              Browser Ping
            </TabsTrigger>
            <TabsTrigger value="server-ping" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Server Ping
            </TabsTrigger>
            <TabsTrigger value="status" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Status
            </TabsTrigger>
            <TabsTrigger value="scanner" className="flex items-center gap-2">
              <RefreshCw className="h-4 w-4" />
              Network Scanner
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Ping History
            </TabsTrigger>
            <TabsTrigger value="map" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Map
            </TabsTrigger>
            <TabsTrigger value="license" className="flex items-center gap-2">
              <Key className="h-4 w-4" />
              License
            </TabsTrigger>
            {userRole === 'admin' && ( // Conditionally render User Management tab
              <TabsTrigger value="users" className="flex items-center gap-2">
                <Users className="h-4 w-4" />
                Users
              </TabsTrigger>
            )}
          </TabsList>

          <TabsContent value="dashboard">
            <DashboardContent
              maps={maps}
              currentMapId={currentMapId}
              setCurrentMapId={setCurrentMapId}
              devices={devices}
              dashboardStats={dashboardStats}
              recentActivity={recentActivity}
              isLoading={isLoading}
              fetchMaps={fetchMaps}
              fetchDashboardData={fetchDashboardData}
              licenseStatus={licenseStatus}
              fetchLicenseStatus={fetchLicenseStatus}
            />
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader>
                <CardTitle>Local Network Devices</CardTitle>
                <CardDescription>Monitor the status of devices on your local network</CardDescription>
              </CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">
                    {[...Array(5)].map((_, i) => (
                      <Skeleton key={i} className="h-16 w-full rounded-lg" />
                    ))}
                  </div>
                ) : devices.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground">
                    <Server className="h-12 w-12 mx-auto mb-4" />
                    <p>No devices found. Add devices to start monitoring.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((device) => (
                      <div
                        key={device.id}
                        className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted"
                      >
                        <div className="flex items-center gap-3">
                          {device.status === "online" ? (
                            <Wifi className="h-5 w-5 text-green-500" />
                          ) : device.status === "offline" ? (
                            <WifiOff className="h-5 w-5 text-red-500" />
                          ) : (
                            <Wifi className="h-5 w-5 text-gray-500" />
                          )}
                          <div>
                            <span className="font-medium">{device.name}</span>
                            <p className="text-sm text-muted-foreground">{device.ip_address}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          {device.last_ping && (
                            <div className="text-xs text-muted-foreground">
                              Last ping: {new Date(device.last_ping).toLocaleTimeString()}
                            </div>
                          )}
                          <Badge
                            variant={
                              device.status === "online" ? "default" :
                              device.status === "offline" ? "destructive" : "secondary"
                            }
                          >
                            {device.status || 'unknown'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="ping">
            <PingTest />
          </TabsContent>

          <TabsContent value="server-ping">
            <ServerPingTest />
          </TabsContent>

          <TabsContent value="status">
            <NetworkStatus />
          </TabsContent>

          <TabsContent value="scanner">
            <NetworkScanner />
          </TabsContent>

          <TabsContent value="history">
            <PingHistory />
          </TabsContent>

          <TabsContent value="map">
            <div className="flex items-center gap-2 mb-4">
              <label htmlFor="map-select" className="text-sm font-medium">Select Map:</label>
              <select
                id="map-select"
                className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                value={currentMapId || ''}
                onChange={(e) => setCurrentMapId(e.target.value)}
              >
                {maps.length === 0 ? (
                  <option value="">No maps available</option>
                ) : (
                  maps.map((map) => (
                    <option key={map.id} value={map.id}>
                      {map.name}
                    </option>
                  ))
                )}
              </select>
              <Button onClick={fetchMaps} variant="outline" size="sm">
                <RefreshCw className="h-4 w-4" />
              </Button>
            </div>
            {currentMapId ? (
              <NetworkMap 
                devices={devices} 
                onMapUpdate={fetchDashboardData}
                mapId={currentMapId} 
                canAddDevice={licenseStatus.can_add_device}
                licenseMessage={licenseStatus.license_message}
                userRole={userRole} // Pass userRole to NetworkMap
              />
            ) : (
              <Card className="h-[70vh] flex items-center justify-center">
                <CardContent className="text-center">
                  <Network className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                  <p className="text-muted-foreground">No map selected or available. Please create one in the PHP frontend or select an existing one.</p>
                </CardContent>
              </Card>
            )}
          </TabsContent>

          <TabsContent value="license">
            <LicenseManager licenseStatus={licenseStatus} fetchLicenseStatus={fetchLicenseStatus} />
          </TabsContent>

          {userRole === 'admin' && ( // Conditionally render UserManagement content
            <TabsContent value="users">
              <UserManagement />
            </TabsContent>
          )}
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default MainApp;