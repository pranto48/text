<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$admin_id = $_SESSION['admin_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $message = '<div class="alert-admin-error mb-4">All fields are required.</div>';
    } elseif ($new_password !== $confirm_new_password) {
        $message = '<div class="alert-admin-error mb-4">New passwords do not match.</div>';
    } elseif (strlen($new_password) < 8) {
        $message = '<div class="alert-admin-error mb-4">New password must be at least 8 characters long.</div>';
    } else {
        // Call the function to update admin password
        if (updateAdminPassword($admin_id, $current_password, $new_password)) {
            $message = '<div class="alert-admin-success mb-4">Your password has been changed successfully!</div>';
        } else {
            $message = '<div class="alert-admin-error mb-4">Failed to change password. Please check your current password.</div>';
        }
    }
}

admin_header("Change Admin Password");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Change Admin Password</h1>

<div class="max-w-md mx-auto admin-card p-8">
    <?= $message ?>

    <form action="change_password.php" method="POST" class="space-y-4">
        <div>
            <label for="current_password" class="block text-gray-300 text-sm font-bold mb-2">Current Password:</label>
            <input type="password" id="current_password" name="current_password" class="form-admin-input" required>
        </div>
        <div>
            <label for="new_password" class="block text-gray-300 text-sm font-bold mb-2">New Password:</label>
            <input type="password" id="new_password" name="new_password" class="form-admin-input" required>
        </div>
        <div>
            <label for="confirm_new_password" class="block text-gray-300 text-sm font-bold mb-2">Confirm New Password:</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-admin-input" required>
        </div>
        <button type="submit" class="btn-admin-primary w-full">
            <i class="fas fa-key mr-2"></i>Change Password
        </button>
    </form>
</div>

<?php admin_footer(); ?>