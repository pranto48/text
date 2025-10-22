import { useState, useEffect } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { History, Calendar, BarChart3, Download } from "lucide-react";
import { getPingHistory, type PingStorageResult } from "@/services/pingStorage";
import { showError } from "@/utils/toast";

interface PingHistoryResult extends PingStorageResult {
  created_at: string;
}

const PingHistory = () => {
  const [history, setHistory] = useState<PingHistoryResult[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [filterHost, setFilterHost] = useState("");
  const [limit, setLimit] = useState(20);

  const loadHistory = async () => {
    setIsLoading(true);
    try {
      const data = await getPingHistory(filterHost || undefined, limit);
      setHistory(data as PingHistoryResult[]);
    } catch (error) {
      showError("Failed to load ping history");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadHistory();
  }, [filterHost, limit]);

  const exportToCSV = () => {
    const csvContent = [
      ["Host", "Packet Loss (%)", "Avg Time (ms)", "Min Time (ms)", "Max Time (ms)", "Success", "Timestamp"],
      ...history.map(item => [
        item.host,
        item.packet_loss,
        item.avg_time,
        item.min_time,
        item.max_time,
        item.success ? "Yes" : "No",
        new Date(item.created_at).toLocaleString()
      ])
    ].map(row => row.join(",")).join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "ping-history.csv";
    link.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <History className="h-5 w-5" />
            Ping History
          </CardTitle>
          <CardDescription>
            View historical ping results stored in the database
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2 mb-4">
            <Input
              placeholder="Filter by host (e.g., 192.168.1.1)"
              value={filterHost}
              onChange={(e) => setFilterHost(e.target.value)}
              className="flex-1"
            />
            <Input
              type="number"
              placeholder="Limit"
              value={limit}
              onChange={(e) => setLimit(Math.max(1, parseInt(e.target.value) || 20))}
              className="w-20"
              min="1"
              max="100"
            />
            <Button onClick={loadHistory} variant="outline">
              Refresh
            </Button>
            <Button onClick={exportToCSV} variant="outline">
              <Download className="h-4 w-4 mr-2" />
              Export CSV
            </Button>
          </div>

          {isLoading ? (
            <div className="text-center p-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
              <p className="text-sm text-muted-foreground">Loading history...</p>
            </div>
          ) : history.length === 0 ? (
            <div className="text-center p-8 border rounded-lg bg-muted">
              <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <p className="text-sm text-muted-foreground">
                No ping history found. Perform some pings first to see results here.
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">
                  {history.length} result{history.length !== 1 ? 's' : ''}
                  {filterHost && ` for ${filterHost}`}
                </span>
              </div>

              {history.map((item, index) => (
                <div key={index} className="p-4 border rounded-lg space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <BarChart3 className="h-5 w-5 text-blue-500" />
                      <div>
                        <span className="font-mono text-sm font-medium">{item.host}</span>
                        <p className="text-xs text-muted-foreground">
                          {new Date(item.created_at).toLocaleString()}
                        </p>
                      </div>
                    </div>
                    <Badge variant={item.success ? "default" : "destructive"}>
                      {item.success ? "Success" : "Failed"}
                    </Badge>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-sm">
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Packet Loss:</span>
                      <span className={item.packet_loss > 0 ? "text-orange-500" : "text-green-500"}>
                        {item.packet_loss}%
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Avg Time:</span>
                      <span>{item.avg_time}ms</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Min Time:</span>
                      <span>{item.min_time}ms</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Max Time:</span>
                      <span>{item.max_time}ms</span>
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

export default PingHistory;