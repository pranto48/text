<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user'; // Get current user's role

switch ($action) {
    case 'get_maps':
        // All users can read maps they own
        $stmt = $pdo->prepare("SELECT m.id, m.name, m.type, m.background_color, m.background_image_url, m.updated_at as lastModified, (SELECT COUNT(*) FROM devices WHERE map_id = m.id AND user_id = ?) as deviceCount FROM maps m WHERE m.user_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$current_user_id, $current_user_id]);
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($maps);
        break;

    case 'create_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can create maps
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can create maps.']);
                exit;
            }
            $name = $input['name'] ?? ''; $type = $input['type'] ?? 'lan';
            if (empty($name)) { http_response_code(400); echo json_encode(['error' => 'Name is required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO maps (user_id, name, type) VALUES (?, ?, ?)"); $stmt->execute([$current_user_id, $name, $type]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, name, type, updated_at as lastModified, 0 as deviceCount FROM maps WHERE id = ? AND user_id = ?"); $stmt->execute([$lastId, $current_user_id]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($map);
        }
        break;

    case 'update_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can update maps
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can update maps.']);
                exit;
            }
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Map ID and updates are required']); exit; }
            
            $allowed_fields = ['name', 'background_color', 'background_image_url'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    $params[] = ($value === '') ? null : $value;
                }
            }

            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            
            $params[] = $id; $params[] = $current_user_id;
            $sql = "UPDATE maps SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Map updated successfully.']);
        }
        break;

    case 'delete_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can delete maps
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can delete maps.']);
                exit;
            }
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ? AND user_id = ?"); $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
    case 'get_edges':
        // All users can read edges for maps they own
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE map_id = ? AND user_id = ?");
        $stmt->execute([$map_id, $current_user_id]);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($edges);
        break;

    case 'create_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can create edges
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can create connections.']);
                exit;
            }
            $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$current_user_id, $input['source_id'], $input['target_id'], $input['map_id'], $input['connection_type'] ?? 'cat5']);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$lastId, $current_user_id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'update_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can update edges
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can update connections.']);
                exit;
            }
            $id = $input['id'] ?? null;
            $connection_type = $input['connection_type'] ?? 'cat5';
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("UPDATE device_edges SET connection_type = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$connection_type, $id, $current_user_id]);
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'delete_edge':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can delete edges
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can delete connections.']);
                exit;
            }
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $current_user_id]);
            echo json_encode(['success' => true]);
        }
        break;
    
    case 'import_map':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can import maps
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can import maps.']);
                exit;
            }
            $map_id = $input['map_id'] ?? null;
            $devices = $input['devices'] ?? [];
            $edges = $input['edges'] ?? [];
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            try {
                $pdo->beginTransaction();
                // Delete old data for this user and map
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE map_id = ? AND user_id = ?"); $stmt->execute([$map_id, $current_user_id]);
                $stmt = $pdo->prepare("DELETE FROM devices WHERE map_id = ? AND user_id = ?"); $stmt->execute([$map_id, $current_user_id]);

                // Insert new devices
                $device_id_map = [];
                $sql = "INSERT INTO devices (
                    user_id, name, ip, check_port, type, x, y, map_id, 
                    ping_interval, icon_size, name_text_size, icon_url, 
                    warning_latency_threshold, warning_packetloss_threshold, 
                    critical_latency_threshold, critical_packetloss_threshold, 
                    show_live_ping
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($devices as $device) {
                    $stmt->execute([
                        $current_user_id,
                        $device['name'] ?? 'Unnamed Device',
                        $device['ip'] ?? null,
                        $device['check_port'] ?? null,
                        $device['type'] ?? 'other',
                        $device['x'] ?? null,
                        $device['y'] ?? null,
                        $map_id,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['icon_url'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0
                    ]);
                    $new_id = $pdo->lastInsertId();
                    $device_id_map[$device['id']] = $new_id;
                }

                // Insert new edges
                $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($edges as $edge) {
                    $new_source_id = $device_id_map[$edge['from']] ?? null;
                    $new_target_id = $device_id_map[$edge['to']] ?? null;
                    if ($new_source_id && $new_target_id) {
                        $stmt->execute([$current_user_id, $new_source_id, $new_target_id, $map_id, $edge['connection_type'] ?? 'cat5']);
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Map imported successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;
    
    case 'upload_map_background':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($current_user_role !== 'admin') { // Only admin can upload map backgrounds
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admin users can upload map backgrounds.']);
                exit;
            }
            $mapId = $_POST['map_id'] ?? null;
            if (!$mapId || !isset($_FILES['backgroundFile'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Map ID and background file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id = ?");
            $stmt->execute([$mapId, $current_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Map not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/map_backgrounds/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['backgroundFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $newFileName = 'map_' . $mapId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/map_backgrounds/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE maps SET background_image_url = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$urlPath, $mapId, $current_user_id]);
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;
}