<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$customer_id = $_SESSION['customer_id'];
$message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');

    if (empty($first_name) || empty($last_name)) {
        $message = '<div class="alert-glass-error mb-4">First Name and Last Name are required.</div>';
    } else {
        if (updateCustomerProfile($customer_id, $first_name, $last_name, $address, $phone, $avatar_url)) {
            // Update session name if first/last name changed
            $_SESSION['customer_name'] = $first_name . ' ' . $last_name;
            $message = '<div class="alert-glass-success mb-4">Profile updated successfully!</div>';
        } else {
            $message = '<div class="alert-glass-error mb-4">Failed to update profile. Please try again.</div>';
        }
    }
}

// Fetch customer and profile data
$customer_data = getCustomerData($customer_id);
$profile_data = getProfileData($customer_id);

// Merge data for display
$display_data = array_merge($customer_data, $profile_data);

portal_header("My Profile - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">My Profile</h1>

<?= $message ?>

<div class="max-w-2xl mx-auto glass-card p-8">
    <h2 class="text-2xl font-semibold text-white mb-4">Edit Your Profile</h2>
    <form action="profile.php" method="POST" class="space-y-4">
        <input type="hidden" name="update_profile" value="1">
        
        <div class="flex items-center space-x-4 mb-6">
            <img src="<?= htmlspecialchars($display_data['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($display_data['email']))) . '?d=identicon') ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-2 border-blue-400">
            <div>
                <label for="avatar_url" class="block text-gray-200 text-sm font-bold mb-2">Avatar URL (Optional):</label>
                <input type="url" id="avatar_url" name="avatar_url" class="form-glass-input" value="<?= htmlspecialchars($display_data['avatar_url'] ?? '') ?>" placeholder="e.g., https://example.com/my-avatar.jpg">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="first_name" class="block text-gray-200 text-sm font-bold mb-2">First Name:</label>
                <input type="text" id="first_name" name="first_name" class="form-glass-input" required value="<?= htmlspecialchars($display_data['first_name'] ?? '') ?>">
            </div>
            <div>
                <label for="last_name" class="block text-gray-200 text-sm font-bold mb-2">Last Name:</label>
                <input type="text" id="last_name" name="last_name" class="form-glass-input" required value="<?= htmlspecialchars($display_data['last_name'] ?? '') ?>">
            </div>
        </div>
        
        <div>
            <label for="email" class="block text-gray-200 text-sm font-bold mb-2">Email (Cannot be changed):</label>
            <input type="email" id="email" name="email" class="form-glass-input bg-gray-700 cursor-not-allowed" value="<?= htmlspecialchars($display_data['email'] ?? '') ?>" readonly>
        </div>

        <div>
            <label for="address" class="block text-gray-200 text-sm font-bold mb-2">Address (Optional):</label>
            <input type="text" id="address" name="address" class="form-glass-input" value="<?= htmlspecialchars($display_data['address'] ?? '') ?>">
        </div>
        
        <div>
            <label for="phone" class="block text-gray-200 text-sm font-bold mb-2">Phone (Optional):</label>
            <input type="tel" id="phone" name="phone" class="form-glass-input" value="<?= htmlspecialchars($display_data['phone'] ?? '') ?>">
        </div>

        <button type="submit" class="btn-glass-primary w-full">
            <i class="fas fa-save mr-2"></i>Save Changes
        </button>
    </form>
</div>

<?php portal_footer(); ?>