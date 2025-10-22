<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

// Ensure cart is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$cart_items = $_SESSION['cart'];
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

$payment_status = '';
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $sender_number = trim($_POST['sender_number'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Create the Order
        $status = ($total_amount == 0) ? 'completed' : 'pending_approval'; // Free licenses are completed immediately
        $stmt = $pdo->prepare("INSERT INTO `orders` (customer_id, total_amount, status, payment_method, transaction_id, sender_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $total_amount, $status, $payment_method, $transaction_id, $sender_number]);
        $order_id = $pdo->lastInsertId();

        // 2. Handle License Generation (Only for Free/Completed orders)
        if ($status === 'completed') {
            foreach ($cart_items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];

                $stmt = $pdo->prepare("SELECT max_devices, license_duration_days FROM `products` WHERE id = ?");
                $stmt->execute([$product_id]);
                $product_details = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product_details) {
                    throw new Exception("Product details not found for ID: " . $product_id);
                }

                $max_devices = $product_details['max_devices'];
                $license_duration_days = $product_details['license_duration_days'];

                $license_key = generateLicenseKey();
                $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));

                $stmt = $pdo->prepare("INSERT INTO `licenses` (customer_id, product_id, license_key, status, max_devices, expires_at) VALUES (?, ?, ?, 'active', ?, ?)");
                $stmt->execute([$customer_id, $product_id, $license_key, $max_devices, $expires_at]);

                $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, product_id, quantity, price, license_key_generated) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $quantity, $price, $license_key]);
            }
            $_SESSION['cart'] = []; // Clear cart after successful order
            $pdo->commit();
            header('Location: dashboard.php?order_success=' . $order_id);
            exit;
        }

        // 3. Handle Pending Approval orders (Paid orders)
        if ($status === 'pending_approval') {
            // Insert order items without generating licenses yet
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            }
            $_SESSION['cart'] = []; // Clear cart
            $pdo->commit();
            header('Location: dashboard.php?order_pending=' . $order_id);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payment processing error: " . $e->getMessage());
        $payment_status = 'error';
    }
}

portal_header("Checkout - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Checkout</h1>

<?php if ($payment_status === 'error'): ?>
    <div class="alert-glass-error mb-4">
        Payment failed due to an internal error. Please try again or contact support.
    </div>
<?php endif; ?>

<div class="max-w-2xl mx-auto glass-card p-8">
    <h2 class="text-2xl font-semibold text-white mb-4">Order Details</h2>
    <div class="space-y-3 mb-6">
        <?php foreach ($cart_items as $item): ?>
            <div class="flex justify-between items-center border-b border-gray-600 pb-2 text-gray-200">
                <span class="text-lg"><?= htmlspecialchars($item['name']) ?></span>
                <span class="font-bold">$<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-between text-xl font-bold mb-6 border-t border-gray-600 pt-4 text-white">
        <span>Total Amount:</span>
        <span>$<?= htmlspecialchars(number_format($total_amount, 2)) ?></span>
    </div>

    <h2 class="text-2xl font-semibold text-white mb-4">Payment Information</h2>

    <?php if ($total_amount == 0): ?>
        <div class="alert-glass-success mb-6">
            <p class="font-bold">Free License Checkout</p>
            <p class="text-sm">Your total is $0. Click "Confirm Order" to receive your free license immediately.</p>
        </div>
        <form action="payment.php" method="POST">
            <input type="hidden" name="confirm_payment" value="1">
            <input type="hidden" name="payment_method" value="free">
            <button type="submit" class="btn-glass-primary w-full">
                <i class="fas fa-check-circle mr-2"></i>Confirm Order (Free)
            </button>
        </form>
    <?php else: ?>
        <div class="space-y-6">
            <!-- Payment Method Selection -->
            <div class="space-y-4">
                <h3 class="text-xl font-semibold text-white">Select Payment Method</h3>
                
                <!-- Buy Me A Coffee -->
                <div class="p-4 bg-gray-800/50 rounded-lg border border-gray-600">
                    <h4 class="text-lg font-medium text-white mb-2">External Payment Link</h4>
                    <p class="text-gray-300 mb-3">Pay instantly using Buy Me A Coffee.</p>
                    <a href="https://buymeacoffee.com/pranto48" target="_blank" class="btn-glass-primary w-full text-center bg-yellow-500 hover:bg-yellow-600">
                        <i class="fas fa-mug-hot mr-2"></i>Pay via Buy Me A Coffee
                    </a>
                </div>

                <!-- Manual Payment -->
                <div class="p-4 bg-gray-800/50 rounded-lg border border-gray-600">
                    <h4 class="text-lg font-medium text-white mb-2">Manual Payment (Bkash, Rocket, Nagad)</h4>
                    <p class="text-gray-300 mb-3">Transfer $<?= htmlspecialchars(number_format($total_amount, 2)) ?> to one of the following numbers (Personal Account):</p>
                    
                    <div class="grid grid-cols-3 gap-4 text-center mb-4">
                        <div class="bg-pink-800/30 p-3 rounded-lg">
                            <strong class="text-pink-300">Bkash</strong>
                            <p class="font-mono text-sm text-white">01915822266</p>
                            <p class="text-xs text-pink-400">(Personal)</p>
                        </div>
                        <div class="bg-red-800/30 p-3 rounded-lg">
                            <strong class="text-red-300">Rocket</strong>
                            <p class="font-mono text-sm text-white">019158222660</p>
                            <p class="text-xs text-red-400">(Personal)</p>
                        </div>
                        <div class="bg-purple-800/30 p-3 rounded-lg">
                            <strong class="text-purple-300">Nagad</strong>
                            <p class="font-mono text-sm text-white">01915822266</p>
                            <p class="text-xs text-purple-400">(Personal)</p>
                        </div>
                    </div>

                    <form action="payment.php" method="POST" class="space-y-4">
                        <input type="hidden" name="confirm_payment" value="1">
                        
                        <div>
                            <label for="payment_method" class="block text-gray-200 text-sm font-bold mb-2">Payment Method Used:</label>
                            <select id="payment_method" name="payment_method" class="form-glass-input" required>
                                <option value="">-- Select Method --</option>
                                <option value="bkash">Bkash</option>
                                <option value="rocket">Rocket</option>
                                <option value="nagad">Nagad</option>
                                <option value="buymeacoffee">Buy Me A Coffee</option>
                            </select>
                        </div>
                        <div>
                            <label for="transaction_id" class="block text-gray-200 text-sm font-bold mb-2">Transaction ID / Reference:</label>
                            <input type="text" id="transaction_id" name="transaction_id" class="form-glass-input" placeholder="Enter Transaction ID" required>
                        </div>
                        <div>
                            <label for="sender_number" class="block text-gray-200 text-sm font-bold mb-2">Sender Number (Optional):</label>
                            <input type="text" id="sender_number" name="sender_number" class="form-glass-input" placeholder="e.g., 01XXXXXXXXX">
                        </div>
                        
                        <button type="submit" class="btn-glass-primary w-full bg-green-600 hover:bg-green-700">
                            <i class="fas fa-paper-plane mr-2"></i>Confirm Manual Payment
                        </button>
                        <p class="text-xs text-gray-400 mt-2 text-center">Your order will be marked as 'Pending Approval' until an admin verifies the payment.</p>
                    </form>
                </div>

                <!-- Bank Transfer (Disabled) -->
                <div class="p-4 bg-gray-800/50 rounded-lg border border-gray-600 opacity-50 cursor-not-allowed">
                    <h4 class="text-lg font-medium text-white mb-2">Bank Transfer</h4>
                    <div class="alert-glass-warning text-center">
                        <i class="fas fa-clock mr-2"></i>Coming Soon
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php portal_footer(); ?>