<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();

// Fetch dashboard stats
$total_customers = $pdo->query("SELECT COUNT(*) FROM `customers`")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
$total_licenses = $pdo->query("SELECT COUNT(*) FROM `licenses`")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM `orders` WHERE status = 'completed'")->fetchColumn() ?: 0;

admin_header("Admin Dashboard");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Admin Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <div class="admin-card text-center p-6">
        <i class="fas fa-users text-5xl text-green-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-gray-100">Total Customers</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_customers) ?></p>
    </div>
    <div class="admin-card text-center p-6">
        <i class="fas fa-box-open text-5xl text-purple-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-gray-100">Total Products</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_products) ?></p>
    </div>
    <div class="admin-card text-center p-6">
        <i class="fas fa-ticket-alt text-5xl text-yellow-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-gray-100">Total Licenses</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_licenses) ?></p>
    </div>
    <div class="admin-card text-center p-6">
        <i class="fas fa-shopping-bag text-5xl text-blue-400 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-gray-100">Total Orders</h2>
        <p class="text-4xl font-bold text-gray-100"><?= htmlspecialchars($total_orders) ?></p>
    </div>
    <div class="admin-card text-center p-6">
        <i class="fas fa-dollar-sign text-5xl text-green-500 mb-4"></i>
        <h2 class="text-2xl font-semibold mb-2 text-gray-100">Total Revenue</h2>
        <p class="text-4xl font-bold text-gray-100">$<?= htmlspecialchars(number_format($total_revenue, 2)) ?></p>
    </div>
</div>

<div class="admin-card mt-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Quick Links</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="users.php" class="btn-admin-primary text-center">Manage Customers</a>
        <a href="license-manager.php" class="btn-admin-primary text-center">Manage Licenses</a>
        <a href="products.php" class="btn-admin-primary text-center">Manage Products</a>
        <a href="tickets.php" class="btn-admin-primary text-center">Manage Tickets</a>
    </div>
</div>

<div class="admin-card mt-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Database Management</h2>
    <div id="db-management-messages" class="mb-4"></div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-800 p-4 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-100 mb-3">Backup Database</h3>
            <p class="text-gray-300 mb-4">Create a full backup of the license database.</p>
            <button id="backupDbBtn" class="btn-admin-primary w-full">
                <i class="fas fa-download mr-2"></i>Backup Database
            </button>
            <div id="backupLoader" class="text-center py-2 hidden">
                <i class="fas fa-spinner fa-spin text-blue-400 text-xl"></i>
                <span class="ml-2 text-gray-300">Creating backup...</span>
            </div>
            <div id="backupDownloadLink" class="mt-4 text-center hidden">
                <a href="#" class="text-green-400 hover:underline" download><i class="fas fa-file-download mr-2"></i>Download Backup</a>
            </div>
        </div>
        <div class="bg-gray-800 p-4 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-100 mb-3">Restore Database</h3>
            <p class="text-red-400 mb-4 font-bold">WARNING: Restoring will overwrite ALL existing data!</p>
            <form id="restoreDbForm" enctype="multipart/form-data" class="space-y-3">
                <input type="file" name="backup_file" accept=".sql" class="form-admin-input" required>
                <button type="submit" id="restoreDbBtn" class="btn-admin-danger w-full">
                    <i class="fas fa-upload mr-2"></i>Restore Database
                </button>
                <div id="restoreLoader" class="text-center py-2 hidden">
                    <i class="fas fa-spinner fa-spin text-blue-400 text-xl"></i>
                    <span class="ml-2 text-gray-300">Restoring database...</span>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="admin-card mt-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">License Automation</h2>
    <p class="text-gray-300 mb-4">Manually trigger the script to deactivate licenses that haven't checked in for over a year. This can also be set up as a cron job on your server.</p>
    <a href="deactivate_old_licenses.php" target="_blank" class="btn-admin-primary w-full">
        <i class="fas fa-clock mr-2"></i>Run Auto-Deactivation Script
    </a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const messagesDiv = document.getElementById('db-management-messages');
        const backupDbBtn = document.getElementById('backupDbBtn');
        const backupLoader = document.getElementById('backupLoader');
        const backupDownloadLink = document.getElementById('backupDownloadLink');
        const restoreDbForm = document.getElementById('restoreDbForm');
        const restoreDbBtn = document.getElementById('restoreDbBtn');
        const restoreLoader = document.getElementById('restoreLoader');

        function showMessage(text, type = 'success') {
            const alertClass = type === 'success' ? 'alert-admin-success' : 'alert-admin-error';
            messagesDiv.innerHTML = `<div class="${alertClass} mb-4">${text}</div>`;
            setTimeout(() => messagesDiv.innerHTML = '', 5000); // Clear message after 5 seconds
        }

        backupDbBtn.addEventListener('click', async () => {
            backupDbBtn.disabled = true;
            backupLoader.classList.remove('hidden');
            backupDownloadLink.classList.add('hidden');
            messagesDiv.innerHTML = '';

            try {
                const response = await fetch('admin_api.php?action=backup_db');
                const data = await response.json();

                if (data.success) {
                    showMessage(data.message, 'success');
                    const downloadAnchor = backupDownloadLink.querySelector('a');
                    downloadAnchor.href = '../' + data.download_url; // Adjust path for client-side download
                    downloadAnchor.textContent = `Download ${data.filename}`;
                    backupDownloadLink.classList.remove('hidden');
                } else {
                    showMessage(data.error || 'An unknown error occurred during backup.', 'error');
                }
            } catch (error) {
                console.error('Backup failed:', error);
                showMessage('Failed to connect to the backup API or an unexpected error occurred.', 'error');
            } finally {
                backupDbBtn.disabled = false;
                backupLoader.classList.add('hidden');
            }
        });

        restoreDbForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!confirm('WARNING: Are you absolutely sure you want to restore the database? This will PERMANENTLY OVERWRITE ALL existing data!')) {
                return;
            }

            restoreDbBtn.disabled = true;
            restoreLoader.classList.remove('hidden');
            messagesDiv.innerHTML = '';

            const formData = new FormData(restoreDbForm);

            try {
                const response = await fetch('admin_api.php?action=restore_db', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showMessage(data.message, 'success');
                    restoreDbForm.reset();
                } else {
                    showMessage(data.error || 'An unknown error occurred during restore.', 'error');
                }
            } catch (error) {
                console.error('Restore failed:', error);
                showMessage('Failed to connect to the restore API or an unexpected error occurred.', 'error');
            } finally {
                restoreDbBtn.disabled = false;
                restoreLoader.classList.add('hidden');
            }
        });
    });
</script>

<?php admin_footer(); ?>