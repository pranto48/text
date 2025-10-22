<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectToDashboard();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        if (authenticateCustomer($email, $password)) {
            redirectToDashboard();
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

portal_header("Login - IT Support BD Portal");
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 absolute inset-0">
    <div class="max-w-md w-full space-y-8 glass-card p-8 form-fade-in">
        <div class="text-center">
            <i class="fas fa-user-circle text-6xl text-blue-300 mb-4"></i>
            <h1 class="text-3xl font-bold text-white mb-2">Welcome Back!</h1>
            <p class="text-gray-300">Sign in to your account to continue.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert-glass-error mb-4">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="mt-8 space-y-6">
            <div>
                <label for="email" class="sr-only">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       class="form-glass-input"
                       placeholder="Email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label for="password" class="sr-only">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="form-glass-input"
                       placeholder="Password">
            </div>
            
            <div>
                <button type="submit" class="btn-glass-primary w-full flex justify-center items-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
            </div>
        </form>
        <p class="text-center text-gray-300 text-sm mt-4">
            Don't have an account? <a href="registration.php" class="text-blue-300 hover:underline font-medium">Register here</a>.
        </p>
    </div>
</div>

<?php portal_footer(); ?>