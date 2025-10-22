<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- External License Validation ---
// This application's license key is now retrieved dynamically from the database.
// The external verification service URL is defined in config.php (LICENSE_API_URL)

$_SESSION['can_add_device'] = false; // Default to false
$_SESSION['license_message'] = 'License validation failed.';
$_SESSION['max_devices'] = 0; // Default max devices
$_SESSION['license_status_code'] = 'unknown'; // 'active', 'expired', 'grace_period', 'disabled', 'error', 'in_use'
$_SESSION['license_grace_period_end'] = null; // Timestamp when grace period ends

// Retrieve the application license key dynamically
$app_license_key = getAppLicenseKey();
$installation_id = getInstallationId(); // Retrieve the installation ID

error_log("DEBUG: auth_check.php - Retrieved app_license_key: " . (empty($app_license_key) ? 'EMPTY' : 'PRESENT') . ", Installation ID: " . (empty($installation_id) ? 'EMPTY' : $installation_id));

if (!$app_license_key) {
    $_SESSION['license_message'] = 'Application license key not configured.';
    $_SESSION['license_status_code'] = 'disabled';
    // Redirect to license setup if key is missing, even if logged in (shouldn't happen if bootstrap works)
    header('Location: license_setup.php');
    exit;
}

if (!$installation_id) {
    $_SESSION['license_message'] = 'Application installation ID not found. Please re-run database setup.';
    $_SESSION['license_status_code'] = 'disabled';
    header('Location: database_setup.php'); // Redirect to setup to ensure ID is generated
    exit;
}

try {
    $pdo = getDbConnection(); // Get DB connection for the AMPNM app

    // Get current device count for the logged-in user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_device_count = $stmt->fetchColumn();

    $last_license_check = getLastLicenseCheck();
    // Changed re-verification interval from 1 month to 1 day
    $one_day_ago = strtotime('-1 day'); 
    $needs_reverification = true;

    if ($last_license_check && strtotime($last_license_check) > $one_day_ago) {
        // If last check was less than a day ago, assume valid unless grace period is active
        $needs_reverification = false;
    }

    // If grace period is active, check if it has expired
    if (isset($_SESSION['license_grace_period_end']) && $_SESSION['license_grace_period_end'] !== null) {
        if (time() > $_SESSION['license_grace_period_end']) {
            // Grace period over, disable app
            $_SESSION['license_status_code'] = 'disabled';
            $_SESSION['license_message'] = 'Your license has expired and the grace period has ended. Please purchase a new license.';
            header('Location: license_expired.php');
            exit;
        } else {
            // Still in grace period, but re-verify if needed
            $_SESSION['license_status_code'] = 'grace_period';
            $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please renew your license.';
            // Even in grace period, if it's been a day since last check, try to re-verify
            if ($needs_reverification) {
                error_log("DEBUG: Re-verifying license during grace period for user {$_SESSION['user_id']}.");
            }
        }
    }

    // Perform re-verification if needed or if no recent check
    if ($needs_reverification || !isset($_SESSION['license_status_code']) || $_SESSION['license_status_code'] === 'unknown') {
        error_log("DEBUG: auth_check.php - Calling revalidateLicenseSession for user {$_SESSION['user_id']}.");
        revalidateLicenseSession($pdo, $_SESSION['user_id']);
    }
} catch (Exception $e) {
    error_log("License API Exception: " . $e->getMessage());
    $_SESSION['license_message'] = 'An unexpected error occurred during license validation.';
    $_SESSION['license_status_code'] = 'error';
    if (!isset($_SESSION['license_grace_period_end']) || $_SESSION['license_grace_period_end'] === null) {
        $_SESSION['license_grace_period_end'] = strtotime('+1 month');
        $_SESSION['license_status_code'] = 'grace_period';
        $_SESSION['license_message'] = 'License verification failed. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please check your connection or renew your license.';
    }
}