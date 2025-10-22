<?php
require_once 'includes/functions.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        $pdo = getLicenseDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM `customers` WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = 'Email already registered. Please login or use a different email.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO `customers` (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$first_name, $last_name, $email, $hashed_password])) {
                $success_message = 'Registration successful! You can now <a href="login.php" class="text-blue-300 hover:underline">login</a>.';
            } else {
                $error_message = 'Something went wrong during registration. Please try again.';
            }
        }
    }
}

portal_header("Register - IT Support BD Portal");
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 absolute inset-0">
    <div class="max-w-md w-full space-y-8 glass-card p-8 form-fade-in">
        <div class="text-center">
            <i class="fas fa-user-plus text-6xl text-blue-300 mb-4"></i>
            <h1 class="text-3xl font-bold text-white mb-2">Create Your Account</h1>
            <p class="text-gray-300">Join us and manage your AMPNM licenses.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert-glass-error mb-4">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert-glass-success mb-4">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <form action="registration.php" method="POST" class="mt-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="sr-only">First Name</label>
                    <input type="text" id="first_name" name="first_name" required
                           class="form-glass-input relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                           placeholder="First Name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div>
                    <label for="last_name" class="sr-only">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required
                           class="form-glass-input relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                           placeholder="Last Name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>
            <div>
                <label for="email" class="sr-only">Email address</label>
                <input type="email" id="email" name="email" autocomplete="email" required
                       class="form-glass-input relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                       placeholder="Email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label for="password" class="sr-only">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required
                       class="form-glass-input relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                       placeholder="Password">
            </div>
            <div>
                <label for="confirm_password" class="sr-only">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required
                       class="form-glass-input relative block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:z-10 focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm"
                       placeholder="Confirm Password">
            </div>
            
            <div>
                <button type="submit" class="btn-glass-primary w-full flex justify-center items-center">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </button>
            </div>
        </form>
        <p class="text-center text-gray-300 text-sm mt-4">
            Already have an account? <a href="login.php" class="text-blue-300 hover:underline font-medium">Login here</a>.
        </p>
    </div>
</div>

<?php portal_footer(); ?>