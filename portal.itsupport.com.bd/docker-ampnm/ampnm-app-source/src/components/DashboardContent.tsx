import { useState, useEffect, useCallback, useMemo } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Activity, Wifi, Server, Clock, RefreshCw, Network, WifiOff, ShieldHalf, BoxOpen, UserCog, Tools } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import {
  NetworkDevice,
  LicenseStatus,
  forceLicenseRecheck,
  Map,
  DashboardStats,
  RecentActivity,
} from "@/services/networkDeviceService";
import { performServerPing } from "@/services/pingService";
import { Skeleton } from "@/components/ui/skeleton";

interface DashboardContentProps {
  maps: Map[];
  currentMapId: string | null;
  setCurrentMapId: (mapId: string | null) => void;
  devices: NetworkDevice[];
  dashboardStats: DashboardStats | null;
  recentActivity: RecentActivity[];
  isLoading: boolean;
  fetchMaps: () => Promise<void>;
  fetchDashboardData: () => Promise<void>;
  licenseStatus: LicenseStatus;
  fetchLicenseStatus: () => Promise<void>;
}

const DashboardContent = ({
  maps,
  currentMapId,
  setCurrentMapId,
  devices,
  dashboardStats,
  recentActivity,
  isLoading,
  fetchMaps,
  fetchDashboardData,
  licenseStatus,
  fetchLicenseStatus,
}: DashboardContentProps) => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [isCheckingDevices, setIsCheckingDevices] = useState(false);
  const [isRefreshingLicense, setIsRefreshingLicense] = useState(false);

  // Auto-ping devices based on their ping interval
  useEffect(() => {
    const intervals: NodeJS.Timeout[] = [];

    devices.forEach((device) => {
      if (device.ping_interval && device.ping_interval > 0 && device.ip_address) {
        const intervalId = setInterval(async () => {
          try {
            console.log(`Auto-pinging ${device.ip_address}`);
            await performServerPing(device.ip_address, 1); // This also updates status in DB
            fetchDashboardData(); // Refresh dashboard data after auto-ping
          } catch (error) {
            console.error(`Auto-ping failed for ${device.ip_address}:`, error);
            // The performServerPing already handles status update to offline on failure
            fetchDashboardData(); // Refresh dashboard data after auto-ping
          }
        }, device.ping_interval * 1000);

        intervals.push(intervalId);
      }
    });

    // Cleanup intervals on component unmount or devices change
    return () => {
      intervals.forEach(clearInterval);
    };
  }, [devices, fetchDashboardData]);

  const checkNetworkStatus = useCallback(async () => {
    try {
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors', cache: 'no-cache' });
      setNetworkStatus(true);
    } catch (error) {
      setNetworkStatus(false);
    }
    setLastChecked(new Date());
  }, []);

  const handleCheckAllDevices = async () => {
    setIsCheckingDevices(true);
    const toastId = showLoading(`Pinging all devices...`);
    try {
      const response = await fetch('/api.php?action=check_all_devices_globally', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ map_id: currentMapId }) // map_id is optional for global check
      });
      if (!response.ok) throw new Error('Failed to ping all devices via server.');
      const result = await response.json();

      if (result.success) {
        dismissToast(toastId);
        showSuccess(`Finished checking all devices. ${result.status_changes} status changes detected.`);
        fetchDashboardData(); // Refresh dashboard data after bulk ping
      } else {
        throw new Error(result.error || "Unknown error during bulk ping.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while checking devices.");
    } finally {
      setIsCheckingDevices(false);
    }
  };

  const handleRefreshLicense = async () => {
    setIsRefreshingLicense(true);
    const toastId = showLoading('Refreshing license status...');
    try {
      await forceLicenseRecheck();
      await fetchLicenseStatus(); // Re-fetch license status to update UI
      dismissToast(toastId);
      showSuccess('License status refreshed.');
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || 'Failed to refresh license status.');
    } finally {
      setIsRefreshingLicense(false);
    }
  };

  useEffect(() => {
    checkNetworkStatus();
    const networkInterval = setInterval(checkNetworkStatus, 60000);
    return () => clearInterval(networkInterval);
  }, [checkNetworkStatus]);

  const statusColorMap: Record<string, string> = {
    online: 'text-green-500',
    warning: 'text-yellow-500',
    critical: 'text-red-500',
    offline: 'text-gray-500',
    unknown: 'text-gray-500',
  };

  const getLicenseBadgeVariant = (statusCode: LicenseStatus['license_status_code']) => {
    switch (statusCode) {
      case 'active': return 'default';
      case 'grace_period': return 'secondary';
      case 'expired':
      case 'disabled':
      case 'error':
      default: return 'destructive';
    }
  };

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {isLoading || !dashboardStats ? (
          <>
            {[...Array(4)].map((_, i) => (
              <Card key={i} className="bg-card border-border">
                <CardHeader>
                  <Skeleton className="h-4 w-3/4" />
                </CardHeader>
                <CardContent>
                  <Skeleton className="h-8 w-1/2" />
                </CardContent>
              </Card>
            ))}
          </>
        ) : (
          <>
            <Card className="bg-card border-border">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Internet Status</CardTitle>
                <Activity className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-primary">{networkStatus ? "Online" : "Offline"}</div>
                <p className="text-xs text-muted-foreground">Internet connectivity</p>
              </CardContent>
            </Card>
            <Card className="bg-card border-border">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Last Check</CardTitle>
                <Clock className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-foreground">{lastChecked.toLocaleTimeString()}</div>
                <p className="text-xs text-muted-foreground">Last status check</p>
              </CardContent>
            </Card>
            <Card className="bg-card border-border">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Devices Online</CardTitle>
                <Wifi className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-500">{dashboardStats.online}/{dashboardStats.total}</div>
                <p className="text-xs text-muted-foreground">Devices online</p>
              </CardContent>
            </Card>
            <Card className="bg-card border-border">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Device Status</CardTitle>
                <Server className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="default" className="text-xs bg-green-500/20 text-green-400">
                    Online {dashboardStats.online}
                  </Badge>
                  <Badge variant="secondary" className="text-xs bg-yellow-500/20 text-yellow-400">
                    Warning {dashboardStats.warning}
                  </Badge>
                  <Badge variant="destructive" className="text-xs bg-red-500/20 text-red-400">
                    Critical {dashboardStats.critical}
                  </Badge>
                  <Badge variant="destructive" className="text-xs bg-gray-500/20 text-gray-400">
                    Offline {dashboardStats.offline}
                  </Badge>
                </div>
              </CardContent>
            </Card>
          </>
        )}
      </div>

      <Card className="bg-card border-border">
        <CardHeader>
          <CardTitle className="flex flex-row items-center justify-between space-y-0 pb-2">
            <div className="flex items-center gap-2 text-primary">
              <Network className="h-5 w-5" />Quick Actions
            </div>
            <div className="flex items-center gap-2">
              <Badge variant={getLicenseBadgeVariant(licenseStatus.license_status_code)} className="text-sm">
                {licenseStatus.license_status_code === 'active' ? `Devices: ${devices.length}/${licenseStatus.max_devices}` : licenseStatus.license_message}
              </Badge>
              {licenseStatus.license_status_code === 'grace_period' && licenseStatus.license_grace_period_end && (
                <Badge variant="secondary" className="text-xs">
                  Grace ends: {new Date(licenseStatus.license_grace_period_end * 1000).toLocaleDateString()}
                </Badge>
              )}
            </div>
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-4">
          <Button onClick={checkNetworkStatus} variant="outline" className="bg-secondary hover:bg-secondary/80 text-foreground border-border">
            <RefreshCw className="h-4 w-4 mr-2" />Check Internet
          </Button>
          <Button
            onClick={handleCheckAllDevices}
            disabled={isCheckingDevices || isLoading}
            variant="outline"
            className="bg-secondary hover:bg-secondary/80 text-foreground border-border"
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${isCheckingDevices ? 'animate-spin' : ''}`} />
            {isCheckingDevices ? 'Checking...' : 'Check All Devices'}
          </Button>
          <Button
            onClick={handleRefreshLicense}
            disabled={isRefreshingLicense}
            variant="outline"
            className="bg-secondary hover:bg-secondary/80 text-foreground border-border"
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${isRefreshingLicense ? 'animate-spin' : ''}`} />
            {isRefreshingLicense ? 'Refreshing...' : 'Refresh License'}
          </Button>
          <div className="flex items-center gap-2">
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
            <Button onClick={fetchMaps} variant="outline" size="sm" className="bg-secondary hover:bg-secondary/80 text-foreground border-border">
              <RefreshCw className="h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="bg-card border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-primary">
            <Clock className="h-5 w-5" />Recent Activity
          </CardTitle>
          <CardDescription>Latest status changes and events across your network.</CardDescription>
        </CardHeader>
        <CardContent>
          {recentActivity.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <Server className="h-12 w-12 mx-auto mb-4" />
              <p>No recent activity for this map.</p>
            </div>
          ) : (
            <div className="space-y-4">
              {recentActivity.map((activity, index) => (
                <div key={index} className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-secondary bg-background border-border">
                  <div className="flex items-center gap-3">
                    <Server className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <span className="font-medium">{activity.device_name}</span>
                      <p className="text-sm text-muted-foreground">{activity.device_ip || 'N/A'}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <Badge variant="secondary" className={`${statusColorMap[activity.status]}`}>
                      {activity.status}
                    </Badge>
                    <div className="text-xs text-muted-foreground">
                      {new Date(activity.created_at).toLocaleTimeString()}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default DashboardContent;