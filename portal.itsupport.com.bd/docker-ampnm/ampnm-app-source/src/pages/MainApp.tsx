import { useState, useEffect, useCallback, useMemo } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Activity,
  Wifi,
  Server,
  Clock,
  RefreshCw,
  Network,
  Key,
  Users,
  Package,
  Settings,
  Map,
  WifiOff,
  Desktop, // Added for Server Ping icon
  Search, // Added for Network Scanner icon
  History, // Added for Ping History icon
  ShieldHalf, // Added for License icon
  BoxOpen, // Added for Products icon
  UserCog, // Added for Users icon
  Tools, // Added for Maintenance icon
} from "lucide-react";
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import { getLicenseStatus, LicenseStatus, User } from "@/services/networkDeviceService";
import { Skeleton } from "@/components/ui/skeleton";
import DashboardContent from "@/components/DashboardContent";
import { useDashboardData } from "@/hooks/useDashboardData";
import LicenseManager from "@/components/LicenseManager";
import UserManagement from "@/components/UserManagement";
import DockerUpdate from "@/components/DockerUpdate";
import Products from "./Products";
import Maintenance from "./Maintenance";
import { Card, CardContent } from "@/components/ui/card";

// Helper to get initial tab from URL hash
const getInitialTab = () => {
  const hash = window.location.hash.substring(1);
  const validTabs = [
    "dashboard", "devices", "ping", "server-ping", "status", "scanner", 
    "history", "map", "license", "products", "users", "maintenance",
  ];
  if (validTabs.includes(hash)) {
    return hash;
  }
  return "dashboard";
};

const MainApp = () => {
  const {
    maps,
    currentMapId,
    setCurrentMapId,
    devices,
    dashboardStats,
    recentActivity,
    isLoading: isDashboardLoading,
    fetchMaps,
    fetchDashboardData,
  } = useDashboardData();

  const [licenseStatus, setLicenseStatus] = useState<LicenseStatus>({
    app_license_key: "",
    can_add_device: false,
    max_devices: 0,
    license_message: "Loading license status...",
    license_status_code: "unknown",
    license_grace_period_end: null,
    installation_id: "",
  });
  const [userRole, setUserRole] = useState<User["role"]>("user");
  const [isUserRoleLoading, setIsUserRoleLoading] = useState(true);
  const [isLicenseStatusLoading, setIsLicenseStatusLoading] = useState(true);
  const [activeTab, setActiveTab] = useState(getInitialTab());

  const fetchLicenseStatus = useCallback(async () => {
    setIsLicenseStatusLoading(true);
    try {
      const status = await getLicenseStatus();
      setLicenseStatus(status);
    } catch (error) {
      console.error("Failed to load license status:", error);
      setLicenseStatus(prev => ({
        ...prev,
        license_message: "Error loading license status.",
        license_status_code: "error",
      }));
    } finally {
      setIsLicenseStatusLoading(false);
    }
  }, []);

  const fetchUserRole = useCallback(async () => {
    setIsUserRoleLoading(true);
    try {
      const response = await fetch('/api.php?action=get_user_info'); 
      if (response.ok) {
        const data = await response.json();
        setUserRole(data.role);
      } else {
        setUserRole('user');
      }
    } catch (error) {
      console.error("Failed to fetch user role:", error);
      setUserRole('user'); 
    } finally {
      setIsUserRoleLoading(false);
    }
  }, []);

  const isAdmin = useMemo(() => userRole === "admin", [userRole]);
  const isAppLoading = isUserRoleLoading || isLicenseStatusLoading;

  useEffect(() => {
    fetchUserRole();
    fetchLicenseStatus();
  }, [fetchUserRole, fetchLicenseStatus]);

  useEffect(() => {
    // Fetch dashboard data only after we know the license status and user role
    if (!isAppLoading) {
      fetchDashboardData();
      fetchMaps();
    }
  }, [isAppLoading, fetchDashboardData, fetchMaps]);

  // Update URL hash when tab changes
  const handleTabChange = (value: string) => {
    setActiveTab(value);
    window.location.hash = value;
  };

  // Listen for hash changes (e.g., back button)
  useEffect(() => {
    const handleHashChange = () => {
      setActiveTab(getInitialTab());
    };
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  if (isAppLoading) {
    return (
      <div className="flex w-full flex-col items-center justify-center min-h-[80vh]">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p className="text-lg text-muted-foreground">Loading application data...</p>
        <p className="text-sm text-muted-foreground mt-2">Fetching user permissions and license status.</p>
      </div>
    );
  }

  return (
    <div className="flex w-full flex-col">
      <div className="flex-1 space-y-4 p-4 pt-6 sm:p-8">
        {/* Temporary debug display for user role */}
        {/* <div className="bg-blue-500/20 text-blue-300 p-2 rounded-md text-sm mb-4">
          Debug: Current User Role is <span className="font-bold capitalize">{userRole}</span>
        </div> */}

        <Tabs value={activeTab} onValueChange={handleTabChange}>
          <TabsList className="flex flex-wrap h-auto p-1">
            <TabsTrigger value="dashboard">
              <Activity className="mr-2 h-4 w-4" />
              Dashboard
            </TabsTrigger>
            <TabsTrigger value="devices">
              <Server className="mr-2 h-4 w-4" />
              Devices
            </TabsTrigger>
            <TabsTrigger value="ping">
              <Wifi className="mr-2 h-4 w-4" />
              Browser Ping
            </TabsTrigger>
            <TabsTrigger value="server-ping">
              <Desktop className="mr-2 h-4 w-4" />
              Server Ping
            </TabsTrigger>
            <TabsTrigger value="status">
              <Network className="mr-2 h-4 w-4" />
              Network Status
            </TabsTrigger>
            <TabsTrigger value="scanner">
              <Search className="mr-2 h-4 w-4" />
              Network Scanner
            </TabsTrigger>
            <TabsTrigger value="history">
              <History className="mr-2 h-4 w-4" />
              Ping History
            </TabsTrigger>
            <TabsTrigger value="map">
              <Map className="mr-2 h-4 w-4" />
              Network Map
            </TabsTrigger>
            <TabsTrigger value="license">
              <ShieldHalf className="mr-2 h-4 w-4" />
              License
            </TabsTrigger>
            <TabsTrigger value="products">
              <BoxOpen className="mr-2 h-4 w-4" />
              Products
            </TabsTrigger>
            {isAdmin && (
              <>
                <TabsTrigger value="users">
                  <UserCog className="mr-2 h-4 w-4" />
                  Users
                </TabsTrigger>
                <TabsTrigger value="maintenance">
                  <Tools className="mr-2 h-4 w-4" />
                  Maintenance
                </TabsTrigger>
              </>
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
              isLoading={isDashboardLoading}
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
                {isDashboardLoading ? (
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
              <label htmlFor="map-select" className="text-sm font-medium text-muted-foreground">Select Map:</label>
              <select
                id="map-select"
                className="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
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
                userRole={userRole}
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
          
          <TabsContent value="products">
            <Products />
          </TabsContent>

          {isAdmin && (
            <TabsContent value="users">
              <UserManagement />
            </TabsContent>
          )}

          {isAdmin && (
            <TabsContent value="maintenance">
              <Maintenance />
            </TabsContent>
          )}
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default MainApp;