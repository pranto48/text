<?php
require_once __DIR__ . '/includes/bootstrap.php'; // Load bootstrap for DB connection and functions

$message = '';

// If a license key is already set, redirect to index
if (getAppLicenseKey()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_license_key = trim($_POST['license_key'] ?? '');
    error_log("DEBUG: license_setup.php received POST with license_key: " . (empty($entered_license_key) ? 'EMPTY' : 'PRESENT'));

    if (empty($entered_license_key)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Please enter a license key.</div>';
    } else {
        // Attempt to validate the license key against the external API
        $installation_id = getInstallationId(); // Ensure installation ID is available
        if (empty($installation_id)) {
            error_log("ERROR: license_setup.php failed to get installation ID.");
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Application installation ID missing. Please re-run database setup.</div>';
        } else {
            $ch = curl_init(LICENSE_API_URL);
            $post_fields = json_encode([
                'app_license_key' => $entered_license_key,
                'user_id' => 'setup_user', // A dummy user ID for initial validation
                'current_device_count' => 0, // No devices yet
                'installation_id' => $installation_id // Pass the unique installation ID
            ]);
            error_log("DEBUG: license_setup.php sending to LICENSE_API_URL: " . LICENSE_API_URL . " with payload: " . $post_fields);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout for the external API call

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("DEBUG: license_setup.php received response: HTTP {$httpCode}, cURL Error: {$curlError}, Body: {$response}");

            if ($response === false) {
                error_log("License API cURL Error during setup: " . $curlError);
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to connect to license verification service.</div>';
            } elseif ($httpCode !== 200) {
                error_log("License API HTTP Error during setup: " . $httpCode . " - Response: " . $response);
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">License verification service returned an error.</div>';
            } else {
                $licenseData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("License API JSON Parse Error during setup: " . json_last_error_msg() . " - Response: " . $response);
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Invalid response from license verification service.</div>';
                } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
                    // License is valid, save it to app_settings
                    if (setAppLicenseKey($entered_license_key)) {
                        error_log("DEBUG: license_setup.php successfully saved license key: " . $entered_license_key);
                        $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center">License key saved successfully! Redirecting...</div>';
                        header('Refresh: 3; url=index.php'); // Redirect to index after 3 seconds
                        exit;
                    } else {
                        error_log("ERROR: license_setup.php failed to save license key to database.");
                        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to save license key to database.</div>';
                    }
                } else {
                    error_log("DEBUG: license_setup.php license validation failed: " . ($licenseData['message'] ?? 'Unknown reason.'));
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">' . ($licenseData['message'] ?? 'Invalid license key.') . '</div>';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM License Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <i class="fas fa-shield-halved text-cyan-400 text-6xl"></i>
            <h1 class="text-3xl font-bold text-white mt-4">AMPNM License Setup</h1>
            <p class="text-slate-400">Please enter your application license key to continue</p>
        </div>
        <form method="POST" action="license_setup.php" class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-xl p-8 space-y-6">
            <?= $message ?>
            <div>
                <label for="license_key" class="block text-sm font-medium text-slate-300 mb-2">License Key</label>
                <input type="text" name="license_key" id="license_key" required
                       class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white"
                       placeholder="XXXX-XXXX-XXXX-XXXX">
            </div>
            <button type="submit"
                    class="w-full px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                Activate License
            </button>
        </form>
    </div>
</body>
</html>