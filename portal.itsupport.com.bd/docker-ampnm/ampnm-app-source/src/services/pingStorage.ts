// import { supabase } from '@/integrations/supabase/client' // No longer needed

export interface PingStorageResult {
  host: string;
  packet_loss: number;
  avg_time: number;
  min_time: number;
  max_time: number;
  success: boolean;
  output?: string;
  created_at?: string;
}

const PHP_API_URL = 'http://localhost:2266/api.php'; // Assuming your PHP API is accessible here

// This function is now redundant as performServerPing already saves to MySQL via PHP.
// It's kept as a placeholder if direct client-side storage to PHP is ever needed.
export const storePingResult = async (result: PingStorageResult) => {
  console.warn("storePingResult is currently a no-op. Ping results are saved via performServerPing -> PHP API.");
  return null;
};

export const getPingHistory = async (host?: string, limit: number = 50) => {
  try {
    const params = new URLSearchParams();
    if (host) {
      params.append('host', host);
    }
    params.append('limit', String(limit));

    const response = await fetch(`${PHP_API_URL}?action=get_ping_history&${params.toString()}`);
    if (!response.ok) {
      throw new Error(`Network response was not ok: ${response.statusText}`);
    }

    const data = await response.json();
    return data as PingStorageResult[];
  } catch (error) {
    console.error('Failed to fetch ping history from PHP API:', error);
    return [];
  }
};

// getPingStats is removed as it was Supabase-specific and not currently used.