<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];

if ($action === 'get_dashboard_data') {
    $map_id = $_GET['map_id'] ?? null;
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required']);
        exit;
    }

    // Get detailed stats for each status
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
        FROM devices WHERE map_id = ? AND user_id = ?
    ");
    $stmt->execute([$map_id, $current_user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure counts are integers, not null
    $stats['total'] = $stats['total'] ?? 0;
    $stats['online'] = $stats['online'] ?? 0;
    $stats['warning'] = $stats['warning'] ?? 0;
    $stats['critical'] = $stats['critical'] ?? 0;
    $stats['offline'] = $stats['offline'] ?? 0;

    // Get devices (minimal data for dashboard list)
    $stmt = $pdo->prepare("SELECT id, name, ip, status, type, last_seen FROM devices WHERE map_id = ? AND user_id = ? ORDER BY name ASC LIMIT 10");
    $stmt->execute([$map_id, $current_user_id]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent status logs for the map's devices
    $stmt = $pdo->prepare("
        SELECT 
            dsl.created_at, 
            dsl.status, 
            dsl.details, 
            d.name as device_name, 
            d.ip as device_ip
        FROM 
            device_status_logs dsl
        JOIN 
            devices d ON dsl.device_id = d.id
        WHERE 
            d.map_id = ? AND d.user_id = ?
        ORDER BY 
            dsl.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$map_id, $current_user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'stats' => $stats,
        'devices' => $devices,
        'recent_activity' => $recent_activity
    ]);
}