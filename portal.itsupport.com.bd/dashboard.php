<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$message = '';

if (isset($_GET['order_success'])) {
    $message = '<div class="alert-glass-success mb-4">Your order #' . htmlspecialchars($_GET['order_success']) . ' has been placed successfully! Your licenses are now available below.</div>';
}

if (isset($_GET['order_pending'])) {
    $message = '<div class="alert-glass-warning mb-4">Order #' . htmlspecialchars($_GET['order_pending']) . ' placed successfully! Your payment is pending approval. Licenses will be issued once payment is confirmed by an administrator.</div>';
}

// Fetch customer's licenses, including product category
$stmt_licenses = $pdo->prepare("
    SELECT l.*, p.name as product_name, p.description as product_description, p.category as product_category
    FROM `licenses` l
    JOIN `products` p ON l.product_id = p.id
    WHERE l.customer_id = ?
    ORDER BY p.category ASC, l.expires_at DESC
");
$stmt_licenses->execute([$customer_id]);
$licenses = $stmt_licenses->fetchAll(PDO::FETCH_ASSOC);

// Group licenses by category
$licenses_by_category = [];
foreach ($licenses as $license) {
    $category = $license['product_category'] ?? 'Uncategorized';
    if (!isset($licenses_by_category[$category])) {
        $licenses_by_category[$category] = [];
    }
    $licenses_by_category[$category][] = $license;
}

// Fetch customer's order history
$stmt_orders = $pdo->prepare("
    SELECT o.*, GROUP_CONCAT(oi.license_key_generated SEPARATOR ', ') as license_keys
    FROM `orders` o
    LEFT JOIN `order_items` oi ON o.id = oi.order_id
    WHERE o.customer_id = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt_orders->execute([$customer_id]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

portal_header("My Dashboard - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Welcome, <?= htmlspecialchars($_SESSION['customer_name']) ?>!</h1>

<?= $message ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-semibold text-white mb-4">My Licenses</h2>
        <?php if (empty($licenses)): ?>
            <div class="text-center py-8 text-gray-200">
                <i class="fas fa-ticket-alt text-6xl text-gray-400 mb-4"></i>
                <p class="text-xl">You don't have any active licenses yet.</p>
                <a href="products.php" class="btn-glass-primary mt-4">Buy Licenses</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($licenses_by_category as $category => $category_licenses): ?>
                    <div class="border-b border-gray-600 pb-4">
                        <h3 class="text-xl font-bold text-blue-300 mb-3"><?= htmlspecialchars($category) ?> Licenses</h3>
                        <div class="space-y-4">
                            <?php foreach ($category_licenses as $license): ?>
                                <div class="glass-card p-4 shadow-sm">
                                    <h4 class="text-lg font-semibold text-white"><?= htmlspecialchars($license['product_name']) ?></h4>
                                    <p class="text-gray-200 text-sm mb-2"><?= htmlspecialchars($license['product_description']) ?></p>
                                    <div class="bg-gray-800 p-3 rounded-md font-mono text-sm break-all mb-2 flex items-center justify-between">
                                        <strong class="text-gray-300">License Key:</strong> <span id="license-key-<?= htmlspecialchars($license['id']) ?>" class="text-white"><?= htmlspecialchars($license['license_key']) ?></span>
                                        <button class="ml-2 text-blue-400 hover:text-blue-300 transition-colors" onclick="copyToClipboard('license-key-<?= htmlspecialchars($license['id']) ?>')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-sm text-gray-200">
                                        <span><strong>Status:</strong> <span class="font-semibold <?= $license['status'] == 'active' || $license['status'] == 'free' ? 'text-green-400' : 'text-red-400' ?>"><?= htmlspecialchars(ucfirst($license['status'])) ?></span></span>
                                        <span><strong>Max Devices:</strong> <?= htmlspecialchars($license['max_devices']) ?></span>
                                        <span><strong>Issued:</strong> <?= date('Y-m-d', strtotime($license['issued_at'])) ?></span>
                                        <span><strong>Expires:</strong> <?= date('Y-m-d', strtotime($license['expires_at'])) ?></span>
                                        <span><strong>Current Devices:</strong> <?= htmlspecialchars($license['current_devices']) ?></span>
                                    </div>
                                    <?php if ($license['status'] == 'active' || $license['status'] == 'free'): ?>
                                        <div class="mt-4">
                                            <a href="license_details.php?license_id=<?= htmlspecialchars($license['id']) ?>" class="btn-glass-primary text-center inline-block">
                                                <i class="fas fa-download mr-2"></i>Download Setup Files
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="glass-card p-6">
        <h2 class="text-2xl font-semibold text-white mb-4">Order History</h2>
        <?php if (empty($orders)): ?>
            <div class="text-center py-8 text-gray-200">
                <i class="fas fa-box-open text-6xl text-gray-400 mb-4"></i>
                <p class="text-xl">You haven't placed any orders yet.</p>
                <a href="products.php" class="btn-glass-primary mt-4">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="glass-card p-4 shadow-sm">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-xl font-semibold text-white">Order #<?= htmlspecialchars($order['id']) ?></h3>
                            <span class="text-sm text-gray-300"><?= date('Y-m-d H:i', strtotime($order['order_date'])) ?></span>
                        </div>
                        <p class="text-gray-200 mb-2"><strong>Total:</strong> $<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></p>
                        <p class="text-gray-200 mb-2">
                            <strong>Status:</strong> 
                            <span class="font-semibold 
                                <?= $order['status'] == 'completed' ? 'text-green-400' : 
                                   ($order['status'] == 'pending_approval' ? 'text-yellow-400' : 'text-red-400') ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                            </span>
                            <?php if ($order['status'] == 'pending_approval'): ?>
                                <span class="text-xs text-yellow-200 ml-2">(Awaiting Admin Approval)</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($order['license_keys'])): ?>
                            <p class="text-gray-200 text-sm"><strong>Licenses:</strong> <span class="font-mono break-all"><?= htmlspecialchars($order['license_keys']) ?></span></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent || element.innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('License key copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            alert('Failed to copy license key.');
        });
    }
</script>

<?php portal_footer(); ?>