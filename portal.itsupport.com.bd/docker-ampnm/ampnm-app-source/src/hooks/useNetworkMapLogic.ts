import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  useNodesState,
  useEdgesState,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
  applyEdgeChanges,
} from 'reactflow';
import {
  addDevice,
  updateDevice,
  deleteDevice,
  NetworkDevice,
  getEdges,
  addEdgeToDB,
  deleteEdgeFromDB,
  updateEdgeInDB,
  importMap,
  MapData,
  User, // Import User interface
} from '@/services/networkDeviceService';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { performServerPing, parsePingOutput } from '@/services/pingService';

interface UseNetworkMapLogicProps {
  initialDevices: NetworkDevice[];
  mapId: string | null;
  canAddDevice: boolean;
  licenseMessage: string;
  userRole: User['role']; // New prop for user role
  onMapUpdate: () => void; // Callback to refresh parent data (e.g., dashboard)
}

export const useNetworkMapLogic = ({
  initialDevices,
  mapId,
  canAddDevice,
  licenseMessage,
  userRole, // Destructure userRole
  onMapUpdate,
}: UseNetworkMapLogicProps) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isDeviceEditorOpen, setIsDeviceEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);

  const canEdit = userRole === 'admin'; // Determine edit permission based on role

  const mapDeviceToNode = useCallback(
    (device: NetworkDevice): Node => ({
      id: device.id!,
      type: 'device',
      position: { x: device.position_x, y: device.position_y },
      data: {
        id: device.id,
        name: device.name,
        ip_address: device.ip_address,
        icon: device.type, // PHP backend uses 'type' for icon
        status: device.status || 'unknown',
        ping_interval: device.ping_interval,
        icon_size: device.icon_size,
        name_text_size: device.name_text_size,
        last_ping: device.last_ping,
        last_ping_result: device.last_ping_result,
        onEdit: (id: string) => handleEditDevice(id),
        onDelete: (id: string) => handleDeleteDevice(id),
        onStatusChange: handleStatusChange,
        canEdit: canEdit, // Pass edit permission to the node component
      },
    }),
    [canEdit] // Dependencies are stable
  );

  // Update nodes when initialDevices change
  useEffect(() => {
    setNodes(initialDevices.map(mapDeviceToNode));
  }, [initialDevices, mapDeviceToNode, setNodes]);

  // Load edges from PHP API
  useEffect(() => {
    const loadEdgesData = async () => {
      if (!mapId) {
        setEdges([]);
        return;
      }
      try {
        const edgesData = await getEdges(mapId);
        setEdges(
          edgesData.map((edge: any) => ({
            id: String(edge.id),
            source: String(edge.source_id),
            target: String(edge.target_id),
            data: { connection_type: edge.connection_type || 'cat5' },
          }))
        );
      } catch (error) {
        console.error('Failed to load network edges:', error);
        showError('Failed to load network connections.');
      }
    };
    loadEdgesData();
  }, [setEdges, mapId]);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      // Optimistically update UI
      setNodes((nds) =>
        nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status } } : node))
      );
      try {
        // Update in database via PHP API
        const device = initialDevices.find(d => d.id === nodeId);
        if (device && device.ip_address) {
          await updateDevice(nodeId, {
            status,
            last_ping: new Date().toISOString(), // PHP backend uses last_seen
            last_ping_result: status === 'online'
          });
          onMapUpdate(); // Trigger a full refresh to get latest data from PHP
        }
      } catch (error) {
        console.error('Failed to update device status in DB:', error);
        showError('Failed to update device status.');
        // Revert UI update on failure
        setNodes((nds) =>
          nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status: device?.status || 'unknown' } } : node))
        );
      }
    },
    [setNodes, initialDevices, onMapUpdate]
  );

  const onConnect = useCallback(
    async (params: Connection) => {
      if (!canEdit) {
        showError('Only admin users can create connections.');
        return;
      }
      if (!mapId) {
        showError('Please select a map before adding connections.');
        return;
      }
      // Optimistically add edge to UI
      const newEdge = {
        id: `reactflow__edge-${params.source}${params.target}`,
        source: params.source!,
        target: params.target!,
        data: { connection_type: 'cat5' }
      };
      setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));

      try {
        // Save to database via PHP API
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: mapId });
        showSuccess('Connection saved.');
        onMapUpdate(); // Refresh map data
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        // Revert UI update on failure
        setEdges((eds) => eds.filter(e => e.id !== newEdge.id));
      }
    },
    [setEdges, mapId, onMapUpdate, canEdit]
  );

  const handleAddDevice = () => {
    if (!canEdit) {
      showError('Only admin users can add devices.');
      return;
    }
    if (!canAddDevice) {
      showError(licenseMessage || 'You have reached your device limit.');
      return;
    }
    setEditingDevice(undefined);
    setIsDeviceEditorOpen(true);
  };

  const handleEditDevice = (deviceId: string) => {
    if (!canEdit) {
      showError('Only admin users can edit devices.');
      return;
    }
    const nodeToEdit = nodes.find((n) => n.id === deviceId);
    if (nodeToEdit) {
      setEditingDevice({ id: nodeToEdit.id, ...nodeToEdit.data });
      setIsDeviceEditorOpen(true);
    }
  };

  const handleDeleteDevice = async (deviceId: string) => {
    if (!canEdit) {
      showError('Only admin users can delete devices.');
      return;
    }
    if (window.confirm('Are you sure you want to delete this device?')) {
      // Optimistically remove from UI
      const originalNodes = nodes;
      setNodes((nds) => nds.filter((node) => node.id !== deviceId));

      try {
        // Delete from database via PHP API
        await deleteDevice(deviceId);
        showSuccess('Device deleted successfully.');
        onMapUpdate(); // Refresh map data
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
        // Revert UI update on failure
        setNodes(originalNodes);
      }
    }
  };

  const handleSaveDevice = async (deviceData: Omit<NetworkDevice, 'id' | 'user_id' | 'position_x' | 'position_y' | 'status' | 'last_ping' | 'last_ping_result' | 'map_name' | 'last_ping_output'>) => {
    if (!canEdit) {
      showError('Only admin users can save device changes.');
      return;
    }
    if (!mapId) {
      showError('Please select a map before adding/editing devices.');
      return;
    }
    try {
      if (editingDevice?.id) {
        // Update existing device
        await updateDevice(editingDevice.id, { ...deviceData, map_id: mapId });
        showSuccess('Device updated successfully.');
      } else {
        // Add new device
        await addDevice({ ...deviceData, position_x: 100, position_y: 100, map_id: mapId });
        showSuccess('Device added successfully.');
      }
      setIsDeviceEditorOpen(false);
      onMapUpdate(); // Refresh the map data
    } catch (error) {
      console.error('Failed to save device:', error);
      showError('Failed to save device.');
    }
  };

  const handlePlaceExistingDevice = useCallback(async (device: NetworkDevice, position: { x: number, y: number }) => {
    if (!canEdit) {
      showError('Only admin users can place devices.');
      return;
    }
    if (!mapId) {
      showError('Please select a map before placing devices.');
      return;
    }
    if (!device.id) return;

    const toastId = showLoading(`Placing ${device.name}...`);
    try {
      // Update device in DB with new map_id and position
      await updateDevice(device.id, {
        map_id: mapId,
        position_x: position.x,
        position_y: position.y,
      });
      dismissToast(toastId);
      showSuccess(`${device.name} placed on map.`);
      onMapUpdate(); // Refresh the map data to show the new node
    } catch (error) {
      dismissToast(toastId);
      console.error('Failed to place device:', error);
      showError('Failed to place device on map.');
    }
  }, [mapId, onMapUpdate, canEdit]);

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      if (!canEdit) {
        showError('Only admin users can move devices.');
        return;
      }
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
        onMapUpdate(); // Refresh map data
      } catch (error) {
        console.error('Failed to save device position:', error);
        showError('Failed to save device position.');
      }
    },
    [onMapUpdate, canEdit]
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
      onEdgesChange(changes);
      changes.forEach(async (change) => {
        if (change.type === 'remove') {
          if (!canEdit) {
            showError('Only admin users can delete connections.');
            return;
          }
          try {
            await deleteEdgeFromDB(change.id);
            showSuccess('Connection deleted.');
            onMapUpdate(); // Refresh map data
          } catch (error) {
            console.error('Failed to delete connection:', error);
            showError('Failed to delete connection.');
          }
        }
      });
    },
    [onEdgesChange, onMapUpdate, canEdit]
  );

  const onEdgeClick = useCallback((_event: React.MouseEvent, edge: Edge) => {
    if (!canEdit) {
      showError('Only admin users can edit connections.');
      return;
    }
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  }, [canEdit]);

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (!canEdit) {
      showError('Only admin users can save connection changes.');
      return;
    }
    // Optimistically update UI
    const originalEdges = edges;
    setEdges((eds) => eds.map(e => e.id === edgeId ? { ...e, data: { connection_type } } : e));

    try {
      // Update in database via PHP API
      await updateEdgeInDB(edgeId, { connection_type });
      showSuccess('Connection updated.');
      onMapUpdate(); // Refresh map data
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
      // Revert UI update on failure
      setEdges(originalEdges);
    }
  };

  const handleImportMap = async (mapData: MapData) => {
    if (!canEdit) {
      showError('Only admin users can import maps.');
      return;
    }
    if (!mapId) {
      showError('Please select a map to import into.');
      return;
    }
    if (!window.confirm('Are you sure you want to import this map? This will overwrite the devices and connections on your current map.')) {
      return;
    }

    const toastId = showLoading('Importing map...');
    try {
      if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
      await importMap(mapData, mapId);
      dismissToast(toastId);
      showSuccess('Map imported successfully!');
      onMapUpdate(); // Refresh the map data
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to import map:', error);
      showError(error.message || 'Failed to import map.');
    }
  };

  const handleExportMap = async () => {
    // All users can export maps
    if (!mapId) {
      showError('No map selected to export.');
      return;
    }
    const exportData: MapData = {
      devices: initialDevices.map(({ user_id, status, last_ping, last_ping_result, map_name, last_ping_output, ...rest }) => ({
        ...rest,
        ip_address: rest.ip_address || '', // Ensure ip_address is always present
        icon: rest.type || 'server', // Ensure icon is always present (using 'type' from PHP)
        position_x: rest.position_x || 0,
        position_y: rest.position_y || 0,
        show_live_ping: rest.show_live_ping || false,
      })),
      edges: edges.map(({ source, target, data }) => ({
        source,
        target,
        connection_type: data.connection_type || 'cat5'
      })),
    };
    const jsonString = JSON.stringify(exportData, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'network-map.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showSuccess('Map exported successfully!');
  };

  const styledEdges = useMemo(() => {
    return edges.map((edge) => {
      const sourceNode = nodes.find((n) => n.id === edge.source);
      const targetNode = nodes.find((n) => n.id === edge.target);
      const isConnectionBroken =
        sourceNode?.data.status === 'offline' ||
        targetNode?.data.status === 'offline';

      const type = edge.data?.connection_type || 'cat5';
      let style: React.CSSProperties = { strokeWidth: 2 };

      if (isConnectionBroken) {
        style.stroke = '#ef4444'; // Red for offline
      } else {
        switch (type) {
          case 'fiber':
            style.stroke = '#f97316'; // Orange
            break;
          case 'wifi':
            style.stroke = '#38bdf8'; // Sky blue
            style.strokeDasharray = '5, 5';
            break;
          case 'radio':
            style.stroke = '#84cc16'; // Lime green
            style.strokeDasharray = '2, 7';
            break;
          case 'cat5':
          default:
            style.stroke = '#a78bfa'; // Violet
            break;
        }
      }

      return {
        ...edge,
        animated: !isConnectionBroken,
        style,
        label: type,
        labelStyle: { fill: 'white', fontWeight: 'bold' }
      };
    });
  }, [nodes, edges]);


  return {
    nodes,
    edges: styledEdges,
    onNodesChange,
    onEdgesChange: onEdgesChangeHandler,
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
    handlePlaceExistingDevice, // Export new function
    canEdit, // Export canEdit status
  };
};