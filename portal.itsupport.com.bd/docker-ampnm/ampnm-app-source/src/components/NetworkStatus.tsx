import { useState, useEffect } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RefreshCw, Wifi, WifiOff, Server, History } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";

interface NetworkCheck {
  timestamp: Date;
  status: boolean;
  type: string;
}

const NetworkStatus = () => {
  const [networkChecks, setNetworkChecks] = useState<NetworkCheck[]>([]);
  const [isChecking, setIsChecking] = useState(false);

  const checkNetwork = async (type: string = "manual") => {
    setIsChecking(true);
    const startTime = new Date();

    try {
      // Test multiple endpoints for better accuracy
      const tests = [
        fetch("https://www.google.com/favicon.ico", { mode: 'no-cors' }),
        fetch("https://cloudflare.com/favicon.ico", { mode: 'no-cors' })
      ];

      await Promise.any(tests);
      
      const result: NetworkCheck = {
        timestamp: startTime,
        status: true,
        type
      };

      setNetworkChecks(prev => [result, ...prev.slice(0, 19)]);
      showSuccess("Network check passed");
    } catch (error) {
      const result: NetworkCheck = {
        timestamp: startTime,
        status: false,
        type
      };

      setNetworkChecks(prev => [result, ...prev.slice(0, 19)]);
      showError("Network check failed");
    } finally {
      setIsChecking(false);
    }
  };

  useEffect(() => {
    // Initial check and set up periodic checks
    checkNetwork("auto");
    const interval = setInterval(() => checkNetwork("auto"), 60000); // Check every minute
    return () => clearInterval(interval);
  }, []);

  const successCount = networkChecks.filter(check => check.status).length;
  const failureCount = networkChecks.filter(check => !check.status).length;
  const uptimePercentage = networkChecks.length > 0 
    ? Math.round((successCount / networkChecks.length) * 100) 
    : 100;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Server className="h-5 w-5" />
            Network Status History
          </CardTitle>
          <CardDescription>
            Monitor your network connectivity over time
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold text-green-600">{successCount}</div>
                <p className="text-sm text-muted-foreground">Successful Checks</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold text-red-600">{failureCount}</div>
                <p className="text-sm text-muted-foreground">Failed Checks</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="text-2xl font-bold">{uptimePercentage}%</div>
                <p className="text-sm text-muted-foreground">Uptime</p>
              </CardContent>
            </Card>
          </div>

          <Button onClick={() => checkNetwork("manual")} disabled={isChecking} className="mb-4">
            <RefreshCw className="h-4 w-4 mr-2" />
            {isChecking ? "Checking..." : "Check Network Now"}
          </Button>

          {networkChecks.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium">Recent Checks</h3>
              {networkChecks.map((check, index) => (
                <div key={index} className="flex items-center justify-between p-2 border rounded">
                  <div className="flex items-center gap-2">
                    {check.status ? (
                      <Wifi className="h-4 w-4 text-green-500" />
                    ) : (
                      <WifiOff className="h-4 w-4 text-red-500" />
                    )}
                    <span className="text-sm">{check.type}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant={check.status ? "default" : "destructive"}>
                      {check.status ? "Online" : "Offline"}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {check.timestamp.toLocaleTimeString()}
                    </span>
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

export default NetworkStatus;