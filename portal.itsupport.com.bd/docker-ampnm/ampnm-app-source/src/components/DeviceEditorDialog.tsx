import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { NetworkDevice } from '@/services/networkDeviceService';
import { Textarea } from '@/components/ui/textarea';
import { useEffect } from 'react';
import { Switch } from '@/components/ui/switch';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"; // Import Accordion components

const deviceSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  ip_address: z.string().optional().nullable(),
  icon: z.string().min(1, 'Type/Icon is required'),
  ping_interval: z.coerce.number().int().positive().optional().nullable(),
  icon_size: z.coerce.number().int().min(20).max(100).optional().nullable(),
  name_text_size: z.coerce.number().int().min(8).max(24).optional().nullable(),
  check_port: z.coerce.number().int().positive().optional().nullable(),
  description: z.string().optional().nullable(),
  warning_latency_threshold: z.coerce.number().int().positive().optional().nullable(),
  warning_packetloss_threshold: z.coerce.number().int().positive().optional().nullable(),
  critical_latency_threshold: z.coerce.number().int().positive().optional().nullable(),
  critical_packetloss_threshold: z.coerce.number().int().positive().optional().nullable(),
  show_live_ping: z.boolean().optional(),
}).refine((data) => {
  if (data.icon !== 'box' && !data.ip_address) {
    return false;
  }
  return true;
}, {
  message: "IP Address is required for non-Box devices",
  path: ["ip_address"],
});

interface DeviceEditorDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (device: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id' | 'status' | 'last_ping' | 'last_ping_result' | 'map_name' | 'last_ping_output'>) => void;
  device?: Partial<NetworkDevice>;
}

const deviceTypes = [
  'box', 'camera', 'cloud', 'database', 'firewall', 'ipphone', 'laptop',
  'mobile', 'nas', 'rack', 'printer', 'punchdevice', 'radio-tower',
  'router', 'server', 'switch', 'tablet', 'wifi-router', 'other'
];

export const DeviceEditorDialog = ({ isOpen, onClose, onSave, device }: DeviceEditorDialogProps) => {
  const form = useForm<z.infer<typeof deviceSchema>>({
    resolver: zodResolver(deviceSchema),
    defaultValues: {
      name: device?.name || '',
      ip_address: device?.ip_address || '',
      icon: device?.icon || 'server',
      ping_interval: device?.ping_interval || undefined,
      icon_size: device?.icon_size || 50,
      name_text_size: device?.name_text_size || 14,
      check_port: device?.check_port || undefined,
      description: device?.description || '',
      warning_latency_threshold: device?.warning_latency_threshold || undefined,
      warning_packetloss_threshold: device?.warning_packetloss_threshold || undefined,
      critical_latency_threshold: device?.critical_latency_threshold || undefined,
      critical_packetloss_threshold: device?.critical_packetloss_threshold || undefined,
      show_live_ping: device?.show_live_ping || false,
    },
  });

  const handleSubmit = (values: z.infer<typeof deviceSchema>) => {
    onSave(values);
    onClose();
  };

  const deviceType = form.watch('icon');
  const isBoxType = deviceType === 'box';

  useEffect(() => {
    if (isBoxType) {
      form.setValue('ip_address', null);
      form.setValue('check_port', null);
      form.setValue('ping_interval', null);
      form.setValue('warning_latency_threshold', null);
      form.setValue('warning_packetloss_threshold', null);
      form.setValue('critical_latency_threshold', null);
      form.setValue('critical_packetloss_threshold', null);
      form.setValue('show_live_ping', false);
      form.clearErrors('ip_address');
    }
  }, [isBoxType, form]);

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[425px] flex flex-col max-h-[90vh]">
        <DialogHeader>
          <DialogTitle>{device?.id ? 'Edit Device' : 'Add Device'}</DialogTitle>
          <DialogDescription>
            {device?.id ? 'Update the details for your network device.' : 'Add a new device to your network map.'}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(handleSubmit)} className="flex-1 overflow-y-auto space-y-6 p-1">
            <Accordion type="multiple" defaultValue={["basic-information"]}> {/* Default open Basic Info */}
              {/* Basic Information Section */}
              <AccordionItem value="basic-information">
                <AccordionTrigger className="text-lg font-semibold text-foreground">Basic Information</AccordionTrigger>
                <AccordionContent className="space-y-4 p-2">
                  <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Name</FormLabel>
                        <FormControl>
                          <Input placeholder="e.g., Main Router" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="icon"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Type / Default Icon</FormLabel>
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder="Select a type" />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {deviceTypes.map((type) => (
                              <SelectItem key={type} value={type} className="capitalize">
                                {type}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="description"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Description (Optional)</FormLabel>
                        <FormControl>
                          <Textarea placeholder="Optional notes about the device" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </AccordionContent>
              </AccordionItem>

              {/* Network Configuration Section (Conditional) */}
              {!isBoxType && (
                <AccordionItem value="network-configuration">
                  <AccordionTrigger className="text-lg font-semibold text-foreground">Network Configuration</AccordionTrigger>
                  <AccordionContent className="space-y-4 p-2">
                    <FormField
                      control={form.control}
                      name="ip_address"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>IP Address</FormLabel>
                          <FormControl>
                            <Input placeholder="e.g., 192.168.1.1" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="check_port"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Service Port (Optional)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              placeholder="e.g., 80 for HTTP"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormDescription>If set, status is based on this port. If empty, it will use ICMP (ping).</FormDescription>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="ping_interval"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Ping Interval (seconds)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              placeholder="e.g., 60 (leave blank for no auto ping)"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormDescription>Automatically ping this device at regular intervals.</FormDescription>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </AccordionContent>
                </AccordionItem>
              )}

              {/* Appearance Section */}
              <AccordionItem value="appearance">
                <AccordionTrigger className="text-lg font-semibold text-foreground">Appearance</AccordionTrigger>
                <AccordionContent className="space-y-4 p-2">
                  <FormField
                    control={form.control}
                    name="icon_size"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{isBoxType ? 'Width' : 'Icon Size'} (20-100px)</FormLabel>
                        <FormControl>
                          <Input
                            type="number"
                            placeholder="e.g., 50"
                            {...field}
                            value={field.value ?? ''}
                            onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="name_text_size"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{isBoxType ? 'Height' : 'Name Text Size'} (8-24px)</FormLabel>
                        <FormControl>
                          <Input
                            type="number"
                            placeholder="e.g., 14"
                            {...field}
                            value={field.value ?? ''}
                            onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  {!isBoxType && (
                    <FormField
                      control={form.control}
                      name="show_live_ping"
                      render={({ field }) => (
                        <FormItem className="flex flex-row items-center justify-between rounded-lg border p-3 shadow-sm">
                          <div className="space-y-0.5">
                            <FormLabel>Show Live Ping Status</FormLabel>
                            <FormDescription>
                              Display real-time ping latency and TTL directly on the device node.
                            </FormDescription>
                          </div>
                          <FormControl>
                            <Switch
                              checked={field.value}
                              onCheckedChange={field.onChange}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  )}
                </AccordionContent>
              </AccordionItem>

              {/* Status Thresholds Section (Conditional) */}
              {!isBoxType && (
                <AccordionItem value="status-thresholds">
                  <AccordionTrigger className="text-lg font-semibold text-foreground">Status Thresholds (Optional)</AccordionTrigger>
                  <AccordionContent className="space-y-4 p-2">
                    <p className="text-sm text-muted-foreground">Define values to trigger 'Warning' or 'Critical' status.</p>
                    <div className="grid grid-cols-2 gap-4">
                      <FormField
                        control={form.control}
                        name="warning_latency_threshold"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-xs">Warn Latency (ms)</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                {...field}
                                value={field.value ?? ''}
                                onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="warning_packetloss_threshold"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-xs">Warn Packet Loss (%)</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                {...field}
                                value={field.value ?? ''}
                                onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="critical_latency_threshold"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-xs">Critical Latency (ms)</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                {...field}
                                value={field.value ?? ''}
                                onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="critical_packetloss_threshold"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-xs">Critical Packet Loss (%)</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                {...field}
                                value={field.value ?? ''}
                                onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </div>
                  </AccordionContent>
                </AccordionItem>
              )}
            </Accordion>
            <DialogFooter className="mt-6">
              <Button type="button" variant="ghost" onClick={onClose}>
                Cancel
              </Button>
              <Button type="submit">Save</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
};