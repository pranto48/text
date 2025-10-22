import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { getUnmappedDevices, NetworkDevice } from '@/services/networkDeviceService';
import { useState, useEffect, useCallback } from 'react';
import { Server, Wifi, RefreshCw } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { showError } from '@/utils/toast';

interface PlaceDeviceDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onPlace: (device: NetworkDevice) => void;
}

export const PlaceDeviceDialog = ({ isOpen, onClose, onPlace }: PlaceDeviceDialogProps) => {
  const [unmappedDevices, setUnmappedDevices] = useState<NetworkDevice[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchUnmappedDevices = useCallback(async () => {
    if (!isOpen) return;
    setIsLoading(true);
    try {
      const devices = await getUnmappedDevices();
      setUnmappedDevices(devices);
    } catch (error) {
      console.error('Failed to fetch unmapped devices:', error);
      showError('Failed to load unassigned devices.');
    } finally {
      setIsLoading(false);
    }
  }, [isOpen]);

  useEffect(() => {
    fetchUnmappedDevices();
  }, [fetchUnmappedDevices]);

  const handlePlace = (device: NetworkDevice) => {
    onPlace(device);
    // Remove the placed device from the local list
    setUnmappedDevices(prev => prev.filter(d => d.id !== device.id));
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="w-full sm:max-w-[450px] flex flex-col max-h-[90vh] bg-card text-foreground"> {/* Adjusted width here */}
        <DialogHeader>
          <DialogTitle className="flex items-center justify-between">
            Place Existing Device
            <Button variant="ghost" size="icon" onClick={fetchUnmappedDevices} disabled={isLoading} className="text-muted-foreground hover:text-primary">
              <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
            </Button>
          </DialogTitle>
          <DialogDescription>
            Select a device from your inventory to place it on the current map.
          </DialogDescription>
        </DialogHeader>
        
        <ScrollArea className="flex-1 p-1">
          <div className="space-y-3">
            {isLoading ? (
              [...Array(4)].map((_, i) => (
                <div key={i} className="flex items-center justify-between p-3 border rounded-lg bg-background border-border">
                  <Skeleton className="h-5 w-3/4" />
                  <Skeleton className="h-8 w-16" />
                </div>
              ))
            ) : unmappedDevices.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Server className="h-12 w-12 mx-auto mb-4" />
                <p>No unassigned devices found in your inventory.</p>
              </div>
            ) : (
              unmappedDevices.map((device) => (
                <div key={device.id} className="flex items-center justify-between p-3 border rounded-lg bg-background border-border hover:bg-secondary transition-colors">
                  <div className="flex items-center gap-3">
                    <Server className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <span className="font-medium">{device.name}</span>
                      <p className="text-xs text-muted-foreground font-mono">{device.ip_address || 'No IP'}</p>
                    </div>
                  </div>
                  <Button 
                    size="sm" 
                    onClick={() => handlePlace(device)}
                    className="h-8 bg-primary hover:bg-primary/90 text-primary-foreground"
                  >
                    Place
                  </Button>
                </div>
              ))
            )}
          </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
};