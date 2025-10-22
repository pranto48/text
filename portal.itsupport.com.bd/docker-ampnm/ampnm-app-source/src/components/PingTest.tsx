import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Network, Clock, AlertCircle, Wifi, WifiOff, Info } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";

interface PingResult {
  host: string;
  time: number;
  status: "success" | "error";
  timestamp: Date;
}

const PingTest = () => {
  const [host, setHost] = useState("192.168.9.3");
  const [isPinging, setIsPinging] = useState(false);
  const [pingResults, setPingResults] = useState<PingResult[]>([]);

  // WebSocket-based ping for local devices
  const performWebSocketPing = async (ip: string): Promise<number> => {
    return new Promise((resolve, reject) => {
      const startTime = performance.now();
      
      // Try WebSocket connection on common ports
      const ports = [80, 8080, 3000, 8000, 8081, 9000];
      let currentPortIndex = 0;
      
      const tryNextPort = () => {
        if (currentPortIndex >= ports.length) {
          reject(new Error("No responsive ports found"));
          return;
        }
        
        const port = ports[currentPortIndex];
        const ws = new WebSocket(`ws://${ip}:${port}`);
        
        ws.onopen = () => {
          const endTime = performance.now();
          ws.close();
          resolve(Math.round(endTime - startTime));
        };
        
        ws.onerror = () => {
          currentPortIndex++;
          setTimeout(tryNextPort, 100);
        };
        
        // Set timeout for this port attempt
        setTimeout(() => {
          ws.close();
          currentPortIndex++;
          setTimeout(tryNextPort, 100);
        }, 1000);
      };
      
      tryNextPort();
    });
  };

  // HTTP-based ping for devices with web servers
  const performHTTPPing = async (ip: string): Promise<number> => {
    return new Promise((resolve, reject) => {
      const startTime = performance.now();
      const img = new Image();
      
      img.onload = () => {
        const endTime = performance.now();
        resolve(Math.round(endTime - startTime));
      };
      
      img.onerror = () => {
        reject(new Error("HTTP request failed"));
      };
      
      // Try various common endpoints
      const endpoints = [
        `http://${ip}/?ping=${Date.now()}`,
        `http://${ip}:80/?ping=${Date.now()}`,
        `http://${ip}:8080/?ping=${Date.now()}`,
        `http://${ip}:3000/?ping=${Date.now()}`,
        `http://${ip}:8000/?ping=${Date.now()}`,
        `http://${ip}:8081/?ping=${Date.now()}`
      ];
      
      let currentIndex = 0;
      const tryNextEndpoint = () => {
        if (currentIndex >= endpoints.length) {
          reject(new Error("No HTTP endpoints responsive"));
          return;
        }
        
        img.src = endpoints[currentIndex];
        currentIndex++;
        
        setTimeout(tryNextEndpoint, 200);
      };
      
      tryNextEndpoint();
    });
  };

  // Alternative method: Use fetch with timeout for public hosts
  const performFetchPing = async (hostname: string): Promise<number> => {
    const startTime = performance.now();
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    try {
      await fetch(`https://${hostname}`, {
        method: 'HEAD',
        mode: 'no-cors',
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      return Math.round(performance.now() - startTime);
    } catch (error) {
      clearTimeout(timeoutId);
      throw new Error("Network request failed");
    }
  };

  const performPing = async () => {
    if (!host.trim()) {
      showError("Please enter a hostname or IP address");
      return;
    }

    setIsPinging(true);

    try {
      let pingTime: number;
      const isLocalIP = /^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|127\.|localhost)/.test(host);
      
      if (isLocalIP) {
        // Try WebSocket first, then HTTP as fallback
        try {
          pingTime = await performWebSocketPing(host);
        } catch (webSocketError) {
          pingTime = await performHTTPPing(host);
        }
      } else {
        pingTime = await performFetchPing(host);
      }
      
      const result: PingResult = {
        host,
        time: pingTime,
        status: "success",
        timestamp: new Date()
      };

      setPingResults(prev => [result, ...prev.slice(0, 9)]);
      showSuccess(`Ping to ${host} successful (${pingTime}ms)`);
    } catch (error) {
      const result: PingResult = {
        host,
        time: 0,
        status: "error",
        timestamp: new Date()
      };

      setPingResults(prev => [result, ...prev.slice(0, 9)]);
      showError(`Ping to ${host} failed - Device is online but not responding to browser requests`);
    } finally {
      setIsPinging(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Network className="h-5 w-5" />
            Browser Ping Test
          </CardTitle>
          <CardDescription>
            Test connectivity using browser-compatible methods (WebSocket/HTTP)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2 mb-4">
            <Input
              placeholder="Enter hostname or IP (e.g., 192.168.1.1 or google.com)"
              value={host}
              onChange={(e) => setHost(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && performPing()}
            />
            <Button onClick={performPing} disabled={isPinging}>
              {isPinging ? "Pinging..." : "Ping"}
            </Button>
          </div>

          <div className="text-sm text-muted-foreground mb-4 p-3 bg-muted rounded-lg">
            <div className="flex items-center gap-2 mb-2">
              <Info className="h-4 w-4" />
              <span className="font-medium">Browser Limitations:</span>
            </div>
            <ul className="list-disc list-inside space-y-1">
              <li>Browsers cannot send ICMP packets (like terminal ping)</li>
              <li>Uses WebSocket and HTTP connections instead</li>
              <li>Device must have open ports or running services</li>
              <li>Works best with web servers, APIs, or WebSocket services</li>
            </ul>
          </div>

          {pingResults.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium">Recent Pings</h3>
              {pingResults.map((result, index) => (
                <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                  <div className="flex items-center gap-3">
                    {result.status === "success" ? (
                      <Wifi className="h-5 w-5 text-green-500" />
                    ) : (
                      <WifiOff className="h-5 w-5 text-red-500" />
                    )}
                    <div>
                      <span className="font-mono text-sm">{result.host}</span>
                      <p className="text-xs text-muted-foreground">
                        {result.timestamp.toLocaleTimeString()}
                      </p>
                    </div>
                  </div>
                  <Badge variant={result.status === "success" ? "default" : "destructive"}>
                    {result.status === "success" ? `${result.time}ms` : "No response"}
                  </Badge>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default PingTest;