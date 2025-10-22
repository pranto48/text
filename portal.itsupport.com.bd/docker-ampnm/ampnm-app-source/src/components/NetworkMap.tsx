import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  Node,
  useReactFlow,
  ReactFlowProvider, // Import ReactFlowProvider
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle, Upload, Download, Network, Server } from 'lucide-react';
import {
  NetworkDevice,
  MapData,
  User, // Import User interface
} from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import { PlaceDeviceDialog } from './PlaceDeviceDialog'; // Import new dialog
import DeviceNode from './DeviceNode';
import { showError } from '@/utils/toast';
import { useNetworkMapLogic } from '@/hooks/useNetworkMapLogic'; // Import the new hook

const NetworkMap = ({ devices, onMapUpdate, mapId, canAddDevice, licenseMessage, userRole }: { devices: NetworkDevice[]; onMapUpdate: () => void; mapId: string | null; canAddDevice: boolean; licenseMessage: string; userRole: User['role'] }) => {
  const importInputRef = useRef<HTMLInputElement>(null);
  const [isPlaceDeviceOpen, setIsPlaceDeviceOpen] = useState(false);
  const reactFlowInstance = useReactFlow();

  const {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    onNodeDragStop,
    onEdgeClick,
    isDeviceEditorOpen,
    setIsDeviceEditorOpen,
    editingDevice,
    setEditingDevice,
    isEdgeEditorOpen,
    setIsEdgeEditorOpen,
    editingEdge,
    setEditingEdge,
    handleAddDevice,
    handleSaveDevice,
    handleSaveEdge,
    handleImportMap,
    handleExportMap,
    handlePlaceExistingDevice,
    canEdit, // Use canEdit status from the hook
  } = useNetworkMapLogic({
    initialDevices: devices,
    mapId,
    canAddDevice,
    licenseMessage,
    userRole, // Pass userRole to the hook
    onMapUpdate,
  });

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        await handleImportMap(mapData);
      } catch (error: any) {
        console.error('Failed to read map file:', error);
        showError(error.message || 'Failed to read map file.');
      } finally {
        if (importInputRef.current) importInputRef.current.value = '';
      }
    };
    reader.readAsText(file);
  };

  const handlePlaceDevice = useCallback((device: NetworkDevice) => {
    if (!mapId) return;
    
    // Calculate a position near the center of the current view
    const viewport = reactFlowInstance.getViewport();
    const position = {
      x: viewport.x + viewport.width / 2,
      y: viewport.y + viewport.height / 2,
    };

    handlePlaceExistingDevice(device, position);
    setIsPlaceDeviceOpen(false); // Close the dialog after placing
  }, [mapId, reactFlowInstance, handlePlaceExistingDevice]);

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg bg-card">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={canEdit ? onNodeDragStop : undefined} // Only allow drag for admin
        onEdgeClick={canEdit ? onEdgeClick : undefined} // Only allow edge click for admin
        fitView
        fitViewOptions={{ padding: 0.1 }}
        nodesDraggable={canEdit} // Make nodes draggable only for admin
        nodesConnectable={canEdit} // Make nodes connectable only for admin
        elementsSelectable={true} // Allow selection for all users
      >
        <Controls className="[&_button]:bg-card [&_button]:border-border [&_button]:text-foreground [&_button:hover]:bg-primary [&_button:hover]:text-primary-foreground" />
        <MiniMap
          nodeColor={(n) => {
            switch (n.data.status) {
              case 'online': return '#22c55e';
              case 'offline': return '#ef4444';
              default: return '#94a3b8';
            }
          }}
          nodeStrokeWidth={3}
          maskColor="rgba(15, 23, 42, 0.8)"
          className="bg-card"
        />
        <Background gap={16} size={1} color="#444" />
      </ReactFlow>
      <div className="absolute top-4 left-4 flex flex-wrap gap-2">
        <Button onClick={handleAddDevice} size="sm" disabled={!mapId || !canAddDevice || !canEdit} title={!canEdit ? "Only admin users can add devices." : (!canAddDevice ? licenseMessage : '')} className="bg-primary hover:bg-primary/90 text-primary-foreground">
          <PlusCircle className="h-4 w-4 mr-2" />Add Device
        </Button>
        <Button onClick={() => setIsPlaceDeviceOpen(true)} size="sm" variant="secondary" disabled={!mapId || !canEdit} title={!canEdit ? "Only admin users can place devices." : ''}>
          <Server className="h-4 w-4 mr-2" />Place Existing
        </Button>
        <Button onClick={handleExportMap} variant="outline" size="sm" disabled={!mapId} className="bg-card hover:bg-secondary text-foreground border-border">
          <Download className="h-4 w-4 mr-2" />Export
        </Button>
        <Button onClick={() => importInputRef.current?.click()} variant="outline" size="sm" disabled={!mapId || !canEdit} title={!canEdit ? "Only admin users can import maps." : ''} className="bg-card hover:bg-secondary text-foreground border-border">
          <Upload className="h-4 w-4 mr-2" />Import
        </Button>
        <input
          type="file"
          ref={importInputRef}
          onChange={handleFileChange}
          accept="application/json"
          className="hidden"
        />
      </div>
      {isDeviceEditorOpen && (
        <DeviceEditorDialog
          isOpen={isDeviceEditorOpen}
          onClose={() => setIsDeviceEditorOpen(false)}
          onSave={handleSaveDevice}
          device={editingDevice}
        />
      )}
      {isEdgeEditorOpen && (
        <EdgeEditorDialog
          isOpen={isEdgeEditorOpen}
          onClose={() => setIsEdgeEditorOpen(false)}
          onSave={handleSaveEdge}
          edge={editingEdge}
        />
      )}
      {isPlaceDeviceOpen && (
        <PlaceDeviceDialog
          isOpen={isPlaceDeviceOpen}
          onClose={() => setIsPlaceDeviceOpen(false)}
          onPlace={handlePlaceDevice}
        />
      )}
    </div>
  );
};

// Wrap NetworkMap with ReactFlowProvider to enable useReactFlow hook
const NetworkMapWrapper = (props: any) => {
  return (
    <ReactFlowProvider>
      <NetworkMap {...props} />
    </ReactFlowProvider>
  );
};

export default NetworkMapWrapper;