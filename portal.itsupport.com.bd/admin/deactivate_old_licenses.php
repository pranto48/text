<?php
require_once '../includes/functions.php';

// Ensure only authenticated admins can access this script
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo "Unauthorized access. Admin login required.";
    exit;
}

header('Content-Type: text/plain'); // Output as plain text for cron jobs or direct viewing

$pdo = getLicenseDbConnection();
$one_year_ago = date('Y-m-d H:i:s', strtotime('-1 year'));
$deactivated_count = 0;
$messages = [];

try {
    $pdo->beginTransaction();

    // Find licenses that are 'active' or 'free' but haven't checked in for over a year
    // And are not already expired (expiry is handled by verify_license.php)
    $stmt = $pdo->prepare("
        SELECT id, license_key, last_active_at, status
        FROM `licenses`
        WHERE (status = 'active' OR status = 'free')
          AND last_active_at IS NOT NULL
          AND last_active_at < ?
          AND (expires_at IS NULL OR expires_at > NOW()) -- Only consider if not already expired by date
    ");
    $stmt->execute([$one_year_ago]);
    $licenses_to_deactivate = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($licenses_to_deactivate as $license) {
        $stmt_update = $pdo->prepare("UPDATE `licenses` SET status = 'revoked', updated_at = NOW() WHERE id = ?");
        $stmt_update->execute([$license['id']]);
        $deactivated_count++;
        $messages[] = "License '{$license['license_key']}' (ID: {$license['id']}) deactivated. Last active: {$license['last_active_at']}.";
    }

    $pdo->commit();
    $messages[] = "Successfully processed. Total licenses deactivated: {$deactivated_count}.";

} catch (Exception $e) {
    $pdo->rollBack();
    $messages[] = "Error during license deactivation: " . $e->getMessage();
    http_response_code(500);
}

echo implode("\n", $messages);
?>