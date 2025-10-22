<?php
header('Content-Type: application/json');

// Include the license service's database configuration
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);

// Log the received input for debugging
error_log("License verification received input: " . print_r($input, true));

$app_license_key = $input['app_license_key'] ?? null;
$user_id = $input['user_id'] ?? null;
$current_device_count = $input['current_device_count'] ?? 0;
$installation_id = $input['installation_id'] ?? null;

// Use empty() for a more robust check against null, empty strings, and 0
if (empty($app_license_key) || empty($user_id) || empty($installation_id)) {
    error_log("License verification failed: Missing app_license_key, user_id, or installation_id. " . 
              "app_license_key: " . (empty($app_license_key) ? 'MISSING' : 'PRESENT') . 
              ", user_id: " . (empty($user_id) ? 'MISSING' : 'PRESENT') . 
              ", installation_id: " . (empty($installation_id) ? 'MISSING' : 'PRESENT'));
    echo json_encode([
        'success' => false,
        'message' => 'Missing application license key, user ID, or installation ID.',
        'actual_status' => 'invalid_request'
    ]);
    exit;
}

try {
    $pdo = getLicenseDbConnection();

    // 1. Fetch the license from MySQL
    $stmt = $pdo->prepare("SELECT * FROM `licenses` WHERE license_key = ?");
    $stmt->execute([$app_license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired application license key.',
            'actual_status' => 'not_found' // Provide a status for clarity
        ]);
        exit;
    }

    // 2. Check license status and expiry
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        // If not active or free, return the actual status
        echo json_encode([
            'success' => false,
            'message' => 'License is ' . $license['status'] . '.',
            'actual_status' => $license['status'] // Explicitly return the actual status
        ]);
        exit;
    }

    if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
        // Automatically update status to 'expired' if past due
        $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'expired', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        echo json_encode([
            'success' => false,
            'message' => 'License has expired.',
            'actual_status' => 'expired' // Explicitly return expired status
        ]);
        exit;
    }

    // 3. Enforce one-to-one binding using installation_id
    if (empty($license['bound_installation_id'])) {
        // License is not bound, bind it to this installation_id
        $stmt = $pdo->prepare("UPDATE `licenses` SET bound_installation_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$installation_id, $license['id']]);
        error_log("License '{$app_license_key}' bound to new installation ID: {$installation_id}");
    } elseif ($license['bound_installation_id'] !== $installation_id) {
        // License is bound to a different installation_id, deny access
        echo json_encode([
            'success' => false,
            'message' => 'License is already in use by another server.',
            'actual_status' => 'in_use' // New status for license in use elsewhere
        ]);
        exit;
    }

    // Update current_devices count and last_active_at timestamp in the license portal's database
    $stmt = $pdo->prepare("UPDATE `licenses` SET current_devices = ?, last_active_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$current_device_count, $license['id']]);

    // Return max_devices to the AMPNM app, which will then calculate can_add_device
    echo json_encode([
        'success' => true,
        'message' => 'License is active.',
        'max_devices' => $license['max_devices'] ?? 1, // Provide max_devices to the AMPNM app
        'actual_status' => $license['status'] // Explicitly return active status
    ]);

} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An internal error occurred during license verification.',
        'actual_status' => 'error' // Provide a status for clarity
    ]);
}
?>