import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Network, Clock, Server, Wifi, WifiOff, AlertCircle } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";
import { performServerPing, parsePingOutput, type PingResult } from "@/services/pingService";
// import { storePingResult } from "@/services/pingStorage"; // No longer needed, PHP backend handles storage
import { updateDeviceStatusByIp } from "@/services/networkDeviceService";

interface ServerPingResult extends PingResult {
  parsedStats?: {
    packetLoss: number;
    avgTime: number;
    minTime: number;
    maxTime: number;
  };
}

const ServerPingTest = () => {
  const [host, setHost] = useState("192.168.9.3");
  const [isPinging, setIsPinging] = useState(false);
  const [pingResults, setPingResults] = useState<ServerPingResult[]>([]);
  const [pingCount, setPingCount] = useState(4);

  const performPing = async () => {
    if (!host.trim()) {
      showError("Please enter a hostname or IP address");
      return;
    }

    setIsPinging(true);

    try {
      const result = await performServerPing(host, pingCount);
      
      const parsedStats = parsePingOutput(result.output);
      const enhancedResult: ServerPingResult = {
        ...result,
        parsedStats
      };

      setPingResults(prev => [enhancedResult, ...prev.slice(0, 9)]);
      
      // The performServerPing function already triggers saving to MySQL via PHP backend.
      // No need to call storePingResult here.

      const newStatus = result.success ? 'online' : 'offline';
      await updateDeviceStatusByIp(result.host, newStatus);

      if (result.success) {
        showSuccess(`Server ping to ${host} successful (${parsedStats.avgTime}ms avg)`);
      } else {
        showError(`Server ping to ${host} failed`);
      }
    } catch (error) {
      showError(`Ping failed: ${error.message}`);
      await updateDeviceStatusByIp(host, 'offline');
    } finally {
      setIsPinging(false);
    }
  };

  const getStatusColor = (result: ServerPingResult) => {
    if (!result.success) return "destructive";
    if (result.parsedStats?.packetLoss === 100) return "destructive";
    if (result.parsedStats?.packetLoss > 0) return "secondary";
    return "default";
  };

  const getStatusText = (result: ServerPingResult) => {
    if (!result.success) return "Failed";
    if (result.parsedStats?.packetLoss === 100) return "No response";
    if (result.parsedStats?.packetLoss > 0) return "Partial loss";
    return "Success";
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            Server Ping Test
          </CardTitle>
          <CardDescription>
            Perform real ICMP pings from the server (not browser)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex gap-2">
              <Input
                placeholder="Enter hostname or IP (e.g., 192.168.1.1 or google.com)"
                value={host}
                onChange={(e) => setHost(e.target.value)}
                className="flex-1"
              />
              <Input
                type="number"
                placeholder="Count"
                value={pingCount}
                onChange={(e) => setPingCount(Math.max(1, parseInt(e.target.value) || 1))}
                className="w-20"
                min="1"
                max="10"
              />
              <Button onClick={performPing} disabled={isPinging}>
                {isPinging ? "Pinging..." : "Ping"}
              </Button>
            </div>

            {isPinging && (
              <div className="space-y-2">
                <Progress value={50} className="w-full" />
                <p className="text-sm text-muted-foreground text-center">
                  Pinging from server...
                </p>
              </div>
            )}

            {pingResults.length > 0 && (
              <div className="space-y-3">
                <h3 className="text-sm font-medium">Recent Server Pings</h3>
                {pingResults.map((result, index) => (
                  <div key={index} className="p-4 border rounded-lg space-y-3">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {result.success && result.parsedStats?.packetLoss !== 100 ? (
                          <Wifi className="h-5 w-5 text-green-500" />
                        ) : (
                          <WifiOff className="h-5 w-5 text-red-500" />
                        )}
                        <div>
                          <span className="font-mono text-sm font-medium">{result.host}</span>
                          <p className="text-xs text-muted-foreground">
                            {new Date(result.timestamp).toLocaleTimeString()}
                          </p>
                        </div>
                      </div>
                      <Badge variant={getStatusColor(result)}>
                        {getStatusText(result)}
                      </Badge>
                    </div>

                    {result.parsedStats && (
                      <div className="grid grid-cols-2 gap-2 text-sm">
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Packet Loss:</span>
                          <span className={result.parsedStats.packetLoss > 0 ? "text-orange-500" : "text-green-500"}>
                            {result.parsedStats.packetLoss}%
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Avg Time:</span>
                          <span>{result.parsedStats.avgTime}ms</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Min Time:</span>
                          <span>{result.parsedStats.minTime}ms</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Max Time:</span>
                          <span>{result.parsedStats.maxTime}ms</span>
                        </div>
                      </div>
                    )}

                    {!result.success && (
                      <div className="text-sm text-red-500 bg-red-50 p-2 rounded">
                        <AlertCircle className="h-4 w-4 inline mr-2" />
                        {result.error || "Ping failed"}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default ServerPingTest;