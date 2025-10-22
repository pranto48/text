import { memo, useState } from 'react';
import { Handle, Position } from 'reactflow';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  Server, 
  Router, 
  Printer, 
  Laptop, 
  Wifi, 
  Database, 
  MoreVertical, 
  Trash2, 
  Edit, 
  Activity,
  WifiOff,
  Clock
} from 'lucide-react';
import { performServerPing, parsePingOutput } from '@/services/pingService';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { showError } from '@/utils/toast';

const iconMap: { [key: string]: React.ComponentType<any> } = {
  server: Server,
  router: Router,
  printer: Printer,
  laptop: Laptop,
  wifi: Wifi,
  database: Database,
};

const DeviceNode = ({ data }: { data: any }) => {
  const [pingResult, setPingResult] = useState<{ time: number; loss: number } | null>(null);
  const [isPinging, setIsPinging] = useState(false);

  const handlePing = async () => {
    if (!data.ip_address) return;
    
    setIsPinging(true);
    setPingResult(null);
    try {
      const result = await performServerPing(data.ip_address, 1);
      const newStatus = result.success ? 'online' : 'offline';
      data.onStatusChange(data.id, newStatus);

      if (result.success) {
        const parsed = parsePingOutput(result.output);
        setPingResult({ time: parsed.avgTime, loss: parsed.packetLoss });
      } else {
        setPingResult({ time: -1, loss: 100 });
      }
    } catch (error: any) {
      console.error(`Ping failed for ${data.ip_address}:`, error);
      showError(`Ping failed: ${error.message}`);
      setPingResult({ time: -1, loss: 100 });
      data.onStatusChange(data.id, 'offline');
    } finally {
      setIsPinging(false);
    }
  };

  const IconComponent = iconMap[data.icon] || Server;
  const iconSize = data.icon_size || 50;
  const nameTextSize = data.name_text_size || 14;

  const statusBorderColor =
    data.status === 'online'
      ? 'border-green-500'
      : data.status === 'offline'
      ? 'border-red-500'
      : 'border-yellow-500';

  const statusIcon = 
    data.status === 'online' ? <Wifi className="h-3 w-3" /> :
    data.status === 'offline' ? <WifiOff className="h-3 w-3" /> :
    <Clock className="h-3 w-3" />;

  return (
    <>
      <Handle type="source" position={Position.Top} />
      <Handle type="source" position={Position.Right} />
      <Handle type="source" position={Position.Bottom} />
      <Handle type="source" position={Position.Left} />
      <Card className={`w-64 shadow-lg bg-gray-800 border-gray-700 text-white border-2 ${statusBorderColor}`}>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 p-3">
          <CardTitle style={{ fontSize: `${nameTextSize}px` }} className="font-medium text-white truncate">
            {data.name}
          </CardTitle>
          <IconComponent style={{ height: `${iconSize}px`, width: `${iconSize}px` }} />
        </CardHeader>
        <CardContent className="p-3">
          <div className="font-mono text-xs text-gray-400 mb-2">{data.ip_address || 'No IP'}</div>
          
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-1">
              {statusIcon}
              <span className="text-xs capitalize">{data.status || 'unknown'}</span>
            </div>
            {data.last_ping && (
              <div className="text-xs text-gray-500">
                {new Date(data.last_ping).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </div>
            )}
          </div>
          
          <div className="flex items-center justify-between">
            <Button 
              size="sm" 
              onClick={handlePing} 
              disabled={isPinging || !data.ip_address}
              className="h-7 text-xs"
            >
              <Activity className={`mr-1 h-3 w-3 ${isPinging ? 'animate-spin' : ''}`} />
              Ping
            </Button>
            {pingResult && (
              <Badge 
                variant={pingResult.loss > 0 ? 'destructive' : 'default'}
                className="h-5 text-xs"
              >
                {pingResult.time >= 0 ? `${pingResult.time}ms` : 'Failed'}
              </Badge>
            )}
          </div>
        </CardContent>
        <div className="absolute top-1 right-1">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-6 w-6">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuItem onClick={() => data.onEdit(data.id)}>
                <Edit className="mr-2 h-4 w-4" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => data.onDelete(data.id)} className="text-red-500">
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </Card>
    </>
  );
};

export default memo(DeviceNode);