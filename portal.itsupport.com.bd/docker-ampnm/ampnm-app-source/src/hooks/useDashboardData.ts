import { useState, useEffect, useCallback } from "react";
import {
  getMaps,
  getFullDashboardData,
  NetworkDevice,
  Map,
  DashboardStats,
  RecentActivity,
} from "@/services/networkDeviceService";
import { showError } from "@/utils/toast";

interface UseDashboardDataResult {
  maps: Map[];
  currentMapId: string | null;
  setCurrentMapId: (mapId: string | null) => void;
  devices: NetworkDevice[];
  dashboardStats: DashboardStats | null;
  recentActivity: RecentActivity[];
  isLoading: boolean;
  fetchMaps: () => Promise<void>;
  fetchDevices: () => Promise<void>;
  fetchDashboardData: () => Promise<void>;
}

export const useDashboardData = (): UseDashboardDataResult => {
  const [maps, setMaps] = useState<Map[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | null>(null);
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [dashboardStats, setDashboardStats] = useState<DashboardStats | null>(null);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchMaps = useCallback(async () => {
    try {
      const phpMaps = await getMaps();
      setMaps(phpMaps);
      if (phpMaps.length > 0 && !currentMapId) {
        setCurrentMapId(phpMaps[0].id);
      } else if (phpMaps.length === 0) {
        setCurrentMapId(null);
      }
    } catch (error) {
      showError("Failed to load maps from database.");
      console.error("Failed to load maps:", error);
    }
  }, [currentMapId]);

  const fetchDashboardData = useCallback(async () => {
    if (!currentMapId) {
      setDashboardStats(null);
      setDevices([]);
      setRecentActivity([]);
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const data = await getFullDashboardData(currentMapId);
      setDashboardStats(data.stats);
      setDevices(data.devices);
      setRecentActivity(data.recent_activity);
    } catch (error) {
      showError("Failed to load dashboard data.");
      console.error("Failed to load dashboard data:", error);
      setDashboardStats(null);
      setDevices([]);
      setRecentActivity([]);
    } finally {
      setIsLoading(false);
    }
  }, [currentMapId]);

  const fetchDevices = useCallback(async () => {
    // This function is now redundant as devices are fetched with dashboard data.
    // Keeping it as a placeholder if a separate device-only fetch is ever needed.
    console.warn("fetchDevices in useDashboardData is a no-op. Devices are fetched via fetchDashboardData.");
  }, []);


  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  return {
    maps,
    currentMapId,
    setCurrentMapId,
    devices,
    dashboardStats,
    recentActivity,
    isLoading,
    fetchMaps,
    fetchDevices,
    fetchDashboardData,
  };
};