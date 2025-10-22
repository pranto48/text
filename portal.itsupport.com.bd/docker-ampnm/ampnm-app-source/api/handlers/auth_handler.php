<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.
// It handles authentication-related API calls.

switch ($action) {
    case 'get_license_status':
        // This information is already set in the session by auth_check.php
        // It should NOT trigger revalidateLicenseSession here to avoid blocking.
        echo json_encode([
            'app_license_key' => getAppLicenseKey(),
            'can_add_device' => $_SESSION['can_add_device'] ?? false,
            'max_devices' => $_SESSION['max_devices'] ?? 0,
            'license_message' => $_SESSION['license_message'] ?? 'License status unknown.',
            'license_status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'license_grace_period_end' => $_SESSION['license_grace_period_end'] ?? null,
            'installation_id' => getInstallationId()
        ]);
        break;
    case 'force_license_recheck':
        // This action explicitly triggers a revalidation and is called by the frontend.
        // It is expected to be blocking as it performs the external API call.
        setLastLicenseCheck(null); // Clear the last_license_check timestamp to force an immediate re-verification
        revalidateLicenseSession($pdo, $_SESSION['user_id']); // Perform the actual revalidation
        echo json_encode([
            'success' => true,
            'message' => 'License re-check triggered.',
            'license_status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'license_message' => $_SESSION['license_message'] ?? 'License status unknown.',
        ]);
        break;
    case 'update_app_license_key':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_license_key = $input['new_license_key'] ?? '';

            if (empty($new_license_key)) {
                http_response_code(400);
                echo json_encode(['error' => 'New license key is required.']);
                exit;
            }

            // Attempt to set the new license key in the database
            if (setAppLicenseKey($new_license_key)) {
                // Force a revalidation to check the new key immediately
                setLastLicenseCheck(null); // Clear last check to force immediate re-verification
                revalidateLicenseSession($pdo, $_SESSION['user_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'License key updated and re-verified.',
                    'license_status_code' => $_SESSION['license_status_code'] ?? 'unknown',
                    'license_message' => $_SESSION['license_message'] ?? 'License status unknown.',
                    'app_license_key' => getAppLicenseKey(),
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save new license key to database.']);
            }
        }
        break;
    case 'get_user_info':
        // Return the current user's role from the session
        // This should NOT trigger any database queries or external API calls.
        echo json_encode([
            'role' => $_SESSION['role'] ?? 'user',
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
        ]);
        break;
}