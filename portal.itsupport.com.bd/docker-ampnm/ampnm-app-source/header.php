<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center gap-2 text-white font-bold">
                        <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>AMPNM</span>
                    </a>
                </div>
                <div class="hidden md:block">
                    <div id="main-nav" class="ml-10 flex items-baseline space-x-1">
                        <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw mr-2"></i>Dashboard</a>
                        <a href="devices.php" class="nav-link"><i class="fas fa-server fa-fw mr-2"></i>Devices</a>
                        <a href="history.php" class="nav-link"><i class="fas fa-history fa-fw mr-2"></i>History</a>
                        <a href="map.php" class="nav-link"><i class="fas fa-project-diagram fa-fw mr-2"></i>Map</a>
                        <a href="status_logs.php" class="nav-link"><i class="fas fa-clipboard-list fa-fw mr-2"></i>Status Logs</a>
                        <a href="email_notifications.php" class="nav-link"><i class="fas fa-envelope fa-fw mr-2"></i>Email Notifications</a>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="users.php" class="nav-link"><i class="fas fa-users-cog fa-fw mr-2"></i>Users</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt fa-fw mr-2"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php if (isset($_SESSION['license_status_code']) && ($_SESSION['license_status_code'] === 'grace_period' || $_SESSION['license_status_code'] === 'expired' || $_SESSION['license_status_code'] === 'error' || $_SESSION['license_status_code'] === 'in_use' || $_SESSION['license_status_code'] === 'disabled')): ?>
        <div class="bg-red-600/20 border-b border-red-500 text-red-300 p-3 text-center text-sm font-medium">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?= htmlspecialchars($_SESSION['license_message']) ?>
            <?php if ($_SESSION['license_status_code'] !== 'disabled'): ?>
                <a href="https://portal.itsupport.com.bd/products.php" target="_blank" class="underline ml-2 hover:text-red-100">Renew License</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="page-content">