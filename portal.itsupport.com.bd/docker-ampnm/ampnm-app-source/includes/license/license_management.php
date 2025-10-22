<?php
require_once __DIR__ . '/../app_settings/app_settings.php'; // For app settings functions
require_once __DIR__ . '/../utils/db_helpers.php'; // For generateUuid

// Function to generate a unique license key
function generateLicenseKey($prefix = 'AMPNM') {
    // Generate a UUID (Universally Unique Identifier)
    // This is a simple way to get a unique string. For stronger keys, consider more complex algorithms.
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord(ord($data[8]) & 0x3f | 0x80)); // set bits 6-7 to 10
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    return strtoupper($prefix . '-' . $uuid);
}

// Helper function to re-evaluate license status and update session variables
function revalidateLicenseSession($pdo, $current_user_id) {
    // Retrieve the application license key dynamically
    $app_license_key = getAppLicenseKey();
    $installation_id = getInstallationId(); // Retrieve the installation ID

    if (!$app_license_key) {
        $_SESSION['license_message'] = 'Application license key not configured.';
        $_SESSION['can_add_device'] = false;
        $_SESSION['max_devices'] = 0;
        $_SESSION['license_status_code'] = 'disabled';
        error_log("DEBUG: revalidateLicenseSession - License key is missing.");
        return;
    }

    if (!$installation_id) {
        $_SESSION['license_message'] = 'Application installation ID not found. Please re-run database setup.';
        $_SESSION['can_add_device'] = false;
        $_SESSION['max_devices'] = 0;
        $_SESSION['license_status_code'] = 'disabled';
        error_log("DEBUG: revalidateLicenseSession - Installation ID is missing.");
        return;
    }

    // Get current device count for the logged-in user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $current_device_count = $stmt->fetchColumn();

    $ch = curl_init(LICENSE_API_URL);
    $post_fields = json_encode([
        'app_license_key' => $app_license_key,
        'user_id' => $current_user_id,
        'current_device_count' => $current_device_count,
        'installation_id' => $installation_id // NEW: Pass the unique installation ID
    ]);
    error_log("DEBUG: revalidateLicenseSession - Sending cURL request to " . LICENSE_API_URL . " with payload: " . $post_fields);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("DEBUG: revalidateLicenseSession - Received response: HTTP {$httpCode}, cURL Error: {$curlError}, Body: {$response}");

    if ($response === false) {
        error_log("License API cURL Error during revalidation: " . $curlError);
        $_SESSION['license_message'] = 'Failed to connect to license verification service.';
        $_SESSION['can_add_device'] = false;
        $_SESSION['license_status_code'] = 'error';
    } elseif ($httpCode !== 200) {
        error_log("License API HTTP Error during revalidation: " . $httpCode . " - Response: " . $response);
        $_SESSION['license_message'] = 'License verification service returned an error.';
        $_SESSION['can_add_device'] = false;
        $_SESSION['license_status_code'] = 'error';
    } else {
        $licenseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("License API JSON Parse Error during revalidation: " . json_last_error_msg() . " - Response: " . $response);
            $_SESSION['license_message'] = 'Invalid response from license verification service.';
            $_SESSION['can_add_device'] = false;
            $_SESSION['license_status_code'] = 'error';
        } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
            $_SESSION['max_devices'] = $licenseData['max_devices'] ?? 0;
            $_SESSION['can_add_device'] = ($current_device_count < $_SESSION['max_devices']);
            $_SESSION['license_message'] = $licenseData['message'] ?? 'License validated successfully.';
            $_SESSION['license_status_code'] = 'active';
            $_SESSION['license_grace_period_end'] = null; // Clear grace period if license is active
            if (!$_SESSION['can_add_device']) {
                $_SESSION['license_message'] = "License active, but you have reached your device limit ({$_SESSION['max_devices']} devices).";
            }
            setLastLicenseCheck(date('Y-m-d H:i:s')); // Update last check timestamp
            error_log("DEBUG: revalidateLicenseSession - License successfully validated. Status: active, Max Devices: {$_SESSION['max_devices']}");
        } else {
            $_SESSION['license_message'] = $licenseData['message'] ?? 'License validation failed.';
            $_SESSION['can_add_device'] = false;
            $_SESSION['license_status_code'] = 'expired';
            // If license is explicitly expired, start grace period
            if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
                $_SESSION['license_grace_period_end'] = strtotime('+1 month');
                $_SESSION['license_status_code'] = 'grace_period';
                $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please renew your license.';
                error_log("DEBUG: revalidateLicenseSession - License expired, entering grace period. Message: {$_SESSION['license_message']}");
            } else {
                error_log("DEBUG: revalidateLicenseSession - License expired, already in grace period. Message: {$_SESSION['license_message']}");
            }
        }
    }
}