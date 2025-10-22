<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin/index.php'); // Redirect to actual admin dashboard
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        if (authenticateAdmin($username, $password)) {
            header('Location: admin/index.php'); // Redirect to actual admin dashboard
            exit;
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}

admin_header("Admin Login");
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 absolute inset-0 admin-body">
    <div class="max-w-md w-full space-y-8 admin-card p-8 form-fade-in">
        <div class="text-center">
            <i class="fas fa-user-shield text-6xl text-blue-400 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-100 mb-2">Admin Login</h1>
            <p class="text-gray-300">Access the License Portal Administration.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert-admin-error mb-4">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="adminpanel.php" method="POST" class="mt-8 space-y-6">
            <div>
                <label for="username" class="sr-only">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required
                       class="form-admin-input"
                       placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div>
                <label for="password" class="sr-only">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="form-admin-input"
                       placeholder="Password">
            </div>
            
            <div>
                <button type="submit" class="btn-admin-primary w-full flex justify-center items-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </div>
        </form>
    </div>
</div>

<?php admin_footer(); ?>