import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { RefreshCw, Server, Wifi, WifiOff, Scan } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";
import { Input } from "@/components/ui/input";

interface ScannedDevice {
  ip: string;
  hostname?: string;
  mac?: string;
  vendor?: string;
  alive: boolean;
}

const NetworkScanner = () => {
  const [isScanning, setIsScanning] = useState(false);
  const [subnet, setSubnet] = useState("192.168.1.0/24"); // Default subnet
  const [scannedDevices, setScannedDevices] = useState<ScannedDevice[]>([]);
  const [scanMessage, setScanMessage] = useState<string | null>(null);

  const scanNetwork = async () => {
    if (!subnet.trim()) {
      showError("Please enter a subnet to scan.");
      return;
    }

    setIsScanning(true);
    setScannedDevices([]);
    setScanMessage("Scanning network... This may take a moment.");

    try {
      const response = await fetch('/api.php?action=scan_network', { // Changed to relative path
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subnet })
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      if (result.devices && Array.isArray(result.devices)) {
        setScannedDevices(result.devices);
        if (result.devices.length > 0) {
          showSuccess(`Found ${result.devices.length} devices on the network.`);
          setScanMessage(null);
        } else {
          showError("No devices found on the network.");
          setScanMessage("No devices found on the network.");
        }
      } else {
        throw new Error("Invalid response format from server.");
      }
    } catch (error: any) {
      console.error("Network scan failed:", error);
      showError(`Network scan failed: ${error.message}. Ensure nmap is installed on the server.`);
      setScanMessage(`Scan failed: ${error.message}. Ensure nmap is installed on the server.`);
    } finally {
      setIsScanning(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card className="bg-card text-foreground border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Scan className="h-5 w-5 text-primary" />
            Network Scanner (Server-side)
          </CardTitle>
          <CardDescription>
            Discover devices on your local network using the server's capabilities (requires nmap)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-2"> {/* Adjusted flex direction here */}
              <Input
                placeholder="Enter subnet (e.g., 192.168.1.0/24)"
                value={subnet}
                onChange={(e) => setSubnet(e.target.value)}
                className="flex-1 bg-background border-border text-foreground"
              />
              <Button 
                onClick={scanNetwork} 
                disabled={isScanning}
                className="bg-primary hover:bg-primary/90 text-primary-foreground"
              >
                <RefreshCw className={`h-4 w-4 mr-2 ${isScanning ? 'animate-spin' : ''}`} />
                {isScanning ? "Scanning..." : "Scan Network"}
              </Button>
            </div>

            {scanMessage && (
              <p className="text-sm text-muted-foreground text-center">
                {scanMessage}
              </p>
            )}

            {scannedDevices.length > 0 && (
              <div className="space-y-3">
                <h3 className="text-sm font-medium">Found Devices ({scannedDevices.length})</h3>
                {scannedDevices.map((device, index) => (
                  <div key={index} className="flex items-center justify-between p-3 border rounded-lg bg-background border-border">
                    <div className="flex items-center gap-3">
                      <Server className="h-5 w-5 text-green-500" />
                      <div>
                        <span className="font-mono text-sm font-medium">{device.ip}</span>
                        {device.hostname && <p className="text-xs text-muted-foreground">Hostname: {device.hostname}</p>}
                      </div>
                    </div>
                    <Badge variant={device.alive ? "default" : "destructive"}>
                      {device.alive ? "Online" : "Offline"}
                    </Badge>
                  </div>
                ))}
              </div>
            )}

            {!isScanning && scannedDevices.length === 0 && !scanMessage && (
              <div className="text-center p-6 border rounded-lg bg-muted border-border">
                <WifiOff className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-sm text-muted-foreground">
                  Enter a subnet and click "Scan Network" to discover devices.
                </p>
                <p className="text-xs text-muted-foreground mt-2">
                  (Requires <a href="https://nmap.org/" target="_blank" className="text-primary hover:underline">nmap</a> to be installed on the server)
                </p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default NetworkScanner;