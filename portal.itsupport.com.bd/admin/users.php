<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();
$message = '';

// Handle delete user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customer_id = (int)$_POST['customer_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `customers` WHERE id = ?");
        $stmt->execute([$customer_id]);
        $message = '<div class="alert-admin-success mb-4">Customer deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error deleting customer: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all customers
$stmt = $pdo->query("SELECT id, first_name, last_name, email, created_at FROM `customers` ORDER BY created_at DESC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("Manage Customers");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Manage Customers</h1>

<?= $message ?>

<div class="admin-card p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">All Customers</h2>
    <?php if (empty($customers)): ?>
        <p class="text-center text-gray-400 py-8">No customers registered yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-700 rounded-lg">
                <thead>
                    <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">ID</th>
                        <th class="py-3 px-6 text-left">Name</th>
                        <th class="py-3 px-6 text-left">Email</th>
                        <th class="py-3 px-6 text-left">Registered On</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-light">
                    <?php foreach ($customers as $customer): ?>
                        <tr class="border-b border-gray-600 hover:bg-gray-600">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?= htmlspecialchars($customer['id']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($customer['email']) ?></td>
                            <td class="py-3 px-6 text-left"><?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?></td>
                            <td class="py-3 px-6 text-center">
                                <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this customer and all associated data (licenses, orders)?');">
                                    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['id']) ?>">
                                    <button type="submit" name="delete_customer" class="btn-admin-danger text-xs px-3 py-1">
                                        <i class="fas fa-trash-alt mr-1"></i>Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>