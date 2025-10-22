<?php
require_once 'includes/functions.php';

// Ensure cart is initialized
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = getLicenseDbConnection();
$message = '';

// Add to cart
if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $pdo->prepare("SELECT id, name, price, max_devices, license_duration_days FROM `products` WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            // For simplicity, we'll just allow one of each product type in cart
            $message = '<div class="alert-glass-error mb-4">Product is already in your cart.</div>';
        } else {
            $_SESSION['cart'][$product_id] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => 1, // Always 1 for license products
                'max_devices' => $product['max_devices'],
                'license_duration_days' => $product['license_duration_days']
            ];
            $message = '<div class="alert-glass-success mb-4">Product added to cart!</div>';
        }
    } else {
        $message = '<div class="alert-glass-error mb-4">Product not found.</div>';
    }
}

// Remove from cart
if (isset($_POST['remove_from_cart']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $message = '<div class="alert-glass-success mb-4">Product removed from cart.</div>';
    }
}

$cart_items = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

portal_header("Your Cart - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Your Shopping Cart</h1>

<?= $message ?>

<?php if (empty($cart_items)): ?>
    <div class="glass-card text-center py-8">
        <i class="fas fa-shopping-cart text-6xl text-gray-400 mb-4"></i>
        <p class="text-xl text-gray-200">Your cart is empty.</p>
        <a href="products.php" class="btn-glass-secondary mt-4">Continue Shopping</a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
            <?php foreach ($cart_items as $item): ?>
                <div class="glass-card flex items-center justify-between p-6">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-file-invoice text-4xl text-blue-300"></i>
                        <div>
                            <h2 class="text-xl font-semibold text-white"><?= htmlspecialchars($item['name']) ?></h2>
                            <p class="text-gray-200">Price: $<?= htmlspecialchars(number_format($item['price'], 2)) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-lg font-bold text-white">$<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></span>
                        <form action="cart.php" method="POST" class="inline-block">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id']) ?>">
                            <button type="submit" name="remove_from_cart" class="text-red-400 hover:text-red-300 transition-colors">
                                <i class="fas fa-trash-alt"></i> Remove
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="lg:col-span-1 glass-card p-6">
            <h2 class="text-2xl font-semibold text-white mb-4">Order Summary</h2>
            <div class="flex justify-between text-lg mb-2 text-gray-200">
                <span>Subtotal:</span>
                <span>$<?= htmlspecialchars(number_format($subtotal, 2)) ?></span>
            </div>
            <div class="flex justify-between text-xl font-bold mb-6 border-t border-gray-600 pt-4 text-white">
                <span>Total:</span>
                <span>$<?= htmlspecialchars(number_format($subtotal, 2)) ?></span>
            </div>
            <a href="payment.php" class="btn-glass-primary w-full text-center">Proceed to Checkout</a>
        </div>
    </div>
<?php endif; ?>

<?php portal_footer(); ?>