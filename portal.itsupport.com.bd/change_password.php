<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$customer_id = $_SESSION['customer_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $message = '<div class="alert-glass-error mb-4">All fields are required.</div>';
    } elseif ($new_password !== $confirm_new_password) {
        $message = '<div class="alert-glass-error mb-4">New passwords do not match.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert-glass-error mb-4">New password must be at least 6 characters long.</div>';
    } else {
        // Call the function to update customer password
        if (updateCustomerPassword($customer_id, $current_password, $new_password)) {
            $message = '<div class="alert-glass-success mb-4">Your password has been changed successfully!</div>';
        } else {
            $message = '<div class="alert-glass-error mb-4">Failed to change password. Please check your current password.</div>';
        }
    }
}

portal_header("Change Password - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Change Your Password</h1>

<div class="max-w-md mx-auto glass-card p-8">
    <?= $message ?>

    <form action="change_password.php" method="POST" class="space-y-4">
        <div>
            <label for="current_password" class="block text-gray-200 text-sm font-bold mb-2">Current Password:</label>
            <input type="password" id="current_password" name="current_password" class="form-glass-input" required>
        </div>
        <div>
            <label for="new_password" class="block text-gray-200 text-sm font-bold mb-2">New Password:</label>
            <input type="password" id="new_password" name="new_password" class="form-glass-input" required>
        </div>
        <div>
            <label for="confirm_new_password" class="block text-gray-200 text-sm font-bold mb-2">Confirm New Password:</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-glass-input" required>
        </div>
        <button type="submit" class="btn-glass-primary w-full">
            <i class="fas fa-key mr-2"></i>Change Password
        </button>
    </form>
</div>

<?php portal_footer(); ?>