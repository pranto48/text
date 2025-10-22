<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();
$message = '';
$filter_status = $_GET['status'] ?? 'all';

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);

    if (isset($_POST['approve_order'])) {
        try {
            $pdo->beginTransaction();
            
            // 1. Fetch order details and items
            $stmt_order = $pdo->prepare("SELECT customer_id, total_amount, status FROM `orders` WHERE id = ?");
            $stmt_order->execute([$order_id]);
            $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

            if (!$order || $order['status'] !== 'pending_approval') {
                throw new Exception("Order not found or not pending approval.");
            }

            $stmt_items = $pdo->prepare("SELECT oi.*, p.max_devices, p.license_duration_days FROM `order_items` oi JOIN `products` p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $stmt_items->execute([$order_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                throw new Exception("Order contains no items.");
            }

            // 2. Generate licenses and update order items
            foreach ($items as $item) {
                $customer_id = $order['customer_id'];
                $product_id = $item['product_id'];
                $max_devices = $item['max_devices'];
                $license_duration_days = $item['license_duration_days'];
                $order_item_id = $item['id'];

                $license_key = generateLicenseKey();
                $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));

                // Insert license
                $stmt_license = $pdo->prepare("INSERT INTO `licenses` (customer_id, product_id, license_key, status, max_devices, expires_at) VALUES (?, ?, ?, 'active', ?, ?)");
                $stmt_license->execute([$customer_id, $product_id, $license_key, $max_devices, $expires_at]);

                // Update order item with generated key
                $stmt_item_update = $pdo->prepare("UPDATE `order_items` SET license_key_generated = ? WHERE id = ?");
                $stmt_item_update->execute([$license_key, $order_item_id]);
            }

            // 3. Update order status to completed
            $stmt_order_update = $pdo->prepare("UPDATE `orders` SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt_order_update->execute([$order_id]);

            $pdo->commit();
            $message = '<div class="alert-admin-success mb-4">Order #' . $order_id . ' approved and licenses generated successfully.</div>';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert-admin-error mb-4">Error approving order: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } elseif (isset($_POST['mark_failed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE `orders` SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$order_id]);
            $message = '<div class="alert-admin-success mb-4">Order #' . $order_id . ' marked as failed.</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert-admin-error mb-4">Error marking order failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- Fetch Orders ---
$sql = "
    SELECT 
        o.*, 
        c.email as customer_email,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM `orders` o
    JOIN `customers` c ON o.customer_id = c.id
    JOIN `order_items` oi ON o.id = oi.order_id
    JOIN `products` p ON oi.product_id = p.id
";
$params = [];
if ($filter_status !== 'all') {
    $sql .= " WHERE o.status = ?";
    $params[] = $filter_status;
}
$sql .= " GROUP BY o.id ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("Manage Orders");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Manage Orders</h1>

<?= $message ?>

<div class="admin-card p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-semibold text-blue-400">All Orders</h2>
        <div class="flex space-x-2">
            <a href="orders.php?status=all" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'all' ? 'bg-blue-700' : '' ?>">All</a>
            <a href="orders.php?status=pending_approval" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'pending_approval' ? 'bg-blue-700' : '' ?>">Pending Approval</a>
            <a href="orders.php?status=completed" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'completed' ? 'bg-blue-700' : '' ?>">Completed</a>
            <a href="orders.php?status=failed" class="btn-admin-primary text-xs px-3 py-1 <?= $filter_status === 'failed' ? 'bg-blue-700' : '' ?>">Failed</a>
        </div>
    </div>
    
    <?php if (empty($orders)): ?>
        <p class="text-center text-gray-400 py-8">No orders found with status "<?= htmlspecialchars($filter_status) ?>".</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-700 rounded-lg">
                <thead>
                    <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">ID</th>
                        <th class="py-3 px-6 text-left">Customer</th>
                        <th class="py-3 px-6 text-left">Products</th>
                        <th class="py-3 px-6 text-left">Amount</th>
                        <th class="py-3 px-6 text-left">Status</th>
                        <th class="py-3 px-6 text-left">Payment Details</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-light">
                    <?php foreach ($orders as $order): ?>
                        <tr class="border-b border-gray-600 hover:bg-gray-600">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?= htmlspecialchars($order['id']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($order['customer_email']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($order['product_names']) ?></td>
                            <td class="py-3 px-6 text-left font-bold">$<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                            <td class="py-3 px-6 text-left">
                                <span class="py-1 px-3 rounded-full text-xs font-semibold 
                                    <?= $order['status'] === 'completed' ? 'bg-green-500' : 
                                       ($order['status'] === 'pending_approval' ? 'bg-yellow-500' : 'bg-red-500') ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-left text-xs">
                                <?php if ($order['total_amount'] > 0): ?>
                                    <strong>Method:</strong> <?= htmlspecialchars(ucfirst($order['payment_method'] ?? 'N/A')) ?><br>
                                    <?php if ($order['transaction_id']): ?>
                                        <strong>TXN ID:</strong> <span class="font-mono"><?= htmlspecialchars($order['transaction_id']) ?></span><br>
                                    <?php endif; ?>
                                    <?php if ($order['sender_number']): ?>
                                        <strong>Sender:</strong> <?= htmlspecialchars($order['sender_number']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-green-400">Free Order</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6 text-center whitespace-nowrap">
                                <?php if ($order['status'] === 'pending_approval'): ?>
                                    <form action="orders.php?status=<?= htmlspecialchars($filter_status) ?>" method="POST" onsubmit="return confirm('Are you sure you want to APPROVE order #<?= htmlspecialchars($order['id']) ?>? This will generate licenses.');" class="inline-block">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                        <button type="submit" name="approve_order" class="btn-admin-primary text-xs px-3 py-1 mr-2">
                                            <i class="fas fa-check mr-1"></i>Approve
                                        </button>
                                    </form>
                                    <form action="orders.php?status=<?= htmlspecialchars($filter_status) ?>" method="POST" onsubmit="return confirm('Are you sure you want to mark order #<?= htmlspecialchars($order['id']) ?> as FAILED?');" class="inline-block">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                        <button type="submit" name="mark_failed" class="btn-admin-danger text-xs px-3 py-1">
                                            <i class="fas fa-times mr-1"></i>Fail
                                        </button>
                                    </form>
                                <?php elseif ($order['status'] === 'completed'): ?>
                                    <span class="text-green-400">Licenses Issued</span>
                                <?php else: ?>
                                    <span class="text-gray-400">No Action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>