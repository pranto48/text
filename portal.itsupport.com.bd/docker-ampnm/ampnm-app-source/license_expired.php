<?php
require_once __DIR__ . '/includes/bootstrap.php';

// If license is somehow active, redirect to index
if (isset($_SESSION['license_status_code']) && $_SESSION['license_status_code'] === 'active') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Expired - AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <i class="fas fa-exclamation-triangle text-red-500 text-6xl"></i>
            <h1 class="text-3xl font-bold text-white mt-4">License Expired</h1>
            <p class="text-slate-400 mt-2">Your AMPNM application license has expired.</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-lg shadow-xl p-8 space-y-6 text-center">
            <p class="text-red-300 text-lg">
                <?= htmlspecialchars($_SESSION['license_message'] ?? 'Your license has expired and the grace period has ended. The application is now disabled.') ?>
            </p>
            <p class="text-slate-300">
                Please contact IT Support BD to renew your license or purchase a new one.
            </p>
            <a href="https://portal.itsupport.com.bd/products.php" target="_blank" class="w-full inline-block px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                <i class="fas fa-shopping-cart mr-2"></i>Purchase New License
            </a>
            <a href="logout.php" class="w-full inline-block px-6 py-3 bg-slate-700 text-slate-300 font-semibold rounded-lg hover:bg-slate-600 focus:ring-2 focus:ring-slate-500 focus:outline-none mt-4">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </div>
</body>
</html>