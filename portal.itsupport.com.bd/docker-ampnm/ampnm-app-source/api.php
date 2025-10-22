<?php
require_once __DIR__ . '/includes/auth_check.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Group actions by handler
$pingActions = ['manual_ping', 'scan_network', 'ping_device', 'get_ping_history'];
$deviceActions = ['get_devices', 'create_device', 'update_device', 'delete_device', 'get_device_details', 'check_device', 'check_all_devices_globally', 'ping_all_devices', 'get_device_uptime', 'upload_device_icon', 'import_devices'];
$mapActions = ['get_maps', 'create_map', 'delete_map', 'get_edges', 'create_edge', 'update_edge', 'delete_edge', 'import_map', 'update_map', 'upload_map_background'];
$dashboardActions = ['get_dashboard_data'];
$userActions = ['get_users', 'create_user', 'delete_user', 'update_user_role']; // Added update_user_role
$logActions = ['get_status_logs'];
$notificationActions = ['get_smtp_settings', 'save_smtp_settings', 'get_device_subscriptions', 'save_device_subscription', 'delete_device_subscription', 'get_all_devices_for_subscriptions'];
$authActions = ['get_license_status', 'force_license_recheck', 'update_app_license_key'];

if (in_array($action, $pingActions)) {
    require __DIR__ . '/api/handlers/ping_handler.php';
} elseif (in_array($action, $deviceActions)) {
    require __DIR__ . '/api/handlers/device_handler.php';
} elseif (in_array($action, $mapActions)) {
    require __DIR__ . '/api/handlers/map_handler.php';
} elseif (in_array($action, $dashboardActions)) {
    require __DIR__ . '/api/handlers/dashboard_handler.php';
} elseif (in_array($action, $userActions)) {
    require __DIR__ . '/api/handlers/user_handler.php';
} elseif (in_array($action, $logActions)) {
    require __DIR__ . '/api/handlers/log_handler.php';
} elseif (in_array($action, $notificationActions)) {
    require __DIR__ . '/api/handlers/notification_handler.php';
} elseif (in_array($action, $authActions)) {
    require __DIR__ . '/api/handlers/auth_handler.php';
} elseif ($action === 'health') {
    echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid action']);
}