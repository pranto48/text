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

// Initialize session variables if they don't exist
if (!isset($_SESSION['can_add_device'])) $_SESSION['can_add_device'] = false;
if (!isset($_SESSION['license_message'])) $_SESSION['license_message'] = 'License status unknown.';
if (!isset($_SESSION['max_devices'])) $_SESSION['max_devices'] = 0;
if (!isset($_SESSION['license_status_code'])) $_SESSION['license_status_code'] = 'unknown';
if (!isset($_SESSION['license_grace_period_end'])) $_SESSION['license_grace_period_end'] = null;

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

// Check if grace period is active and has expired
if (isset($_SESSION['license_grace_period_end']) && $_SESSION['license_grace_period_end'] !== null) {
    if (time() > $_SESSION['license_grace_period_end']) {
        // Grace period over, disable app
        $_SESSION['license_status_code'] = 'disabled';
        $_SESSION['license_message'] = 'Your license has expired and the grace period has ended. Please purchase a new license.';
        header('Location: license_expired.php');
        exit;
    } else {
        // Still in grace period
        if ($_SESSION['license_status_code'] !== 'grace_period') { // Only update if not already set
            $_SESSION['license_status_code'] = 'grace_period';
            $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $_SESSION['license_grace_period_end']) . '. Please renew your license.';
        }
    }
}

// IMPORTANT: The blocking call to revalidateLicenseSession is removed from here.
// License status will now be updated by explicit frontend calls to force_license_recheck
// or update_app_license_key, or by a background process if implemented.
// This ensures the initial page load is not blocked by external API calls.

// If the license status is still 'unknown' after initial checks, it means no revalidation has occurred yet.
// The frontend will handle triggering the first revalidation.
if ($_SESSION['license_status_code'] === 'unknown') {
    $_SESSION['license_message'] = 'License status needs to be verified. Please refresh or check the License tab.';
}
?>