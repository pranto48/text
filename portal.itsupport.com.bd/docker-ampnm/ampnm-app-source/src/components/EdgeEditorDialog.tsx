import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Edge } from 'reactflow';
import { useState } from 'react';

interface EdgeEditorDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (edgeId: string, connectionType: string) => void;
  edge?: Edge;
}

const connectionTypes = ['cat5', 'fiber', 'wifi', 'radio'];

export const EdgeEditorDialog = ({ isOpen, onClose, onSave, edge }: EdgeEditorDialogProps) => {
  const [selectedType, setSelectedType] = useState(edge?.data?.connection_type || 'cat5');

  if (!edge) return null;

  const handleSave = () => {
    onSave(edge.id, selectedType);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Edit Connection</DialogTitle>
          <DialogDescription>
            Change the connection type between devices.
          </DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-4">
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="connection-type" className="text-right">
              Type
            </Label>
            <Select value={selectedType} onValueChange={setSelectedType}>
              <SelectTrigger id="connection-type" className="col-span-3">
                <SelectValue placeholder="Select a type" />
              </SelectTrigger>
              <SelectContent>
                {connectionTypes.map((type) => (
                  <SelectItem key={type} value={type} className="capitalize">
                    {type}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="button" onClick={handleSave}>
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};