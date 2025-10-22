<?php
require_once 'includes/functions.php';

$pdo = getLicenseDbConnection();
$stmt = $pdo->query("SELECT * FROM `products` ORDER BY price ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

portal_header("Our Products - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Our AMPNM License Products</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php if (empty($products)): ?>
        <p class="text-center text-gray-200 col-span-full">No products available at the moment. Please check back later!</p>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <div class="glass-card flex flex-col justify-between p-6">
                <div>
                    <h2 class="text-2xl font-semibold text-white mb-2"><?= htmlspecialchars($product['name']) ?></h2>
                    <p class="text-gray-200 mb-4"><?= htmlspecialchars($product['description']) ?></p>
                    <ul class="list-disc list-inside text-gray-100 mb-4 pl-4">
                        <li>Max Devices: <?= htmlspecialchars($product['max_devices']) ?></li>
                        <li>Duration: <?= htmlspecialchars($product['license_duration_days'] / 365) ?> Year(s)</li>
                    </ul>
                </div>
                <div class="mt-auto pt-4 border-t border-gray-600">
                    <p class="text-3xl font-bold text-blue-300 mb-4">$<?= htmlspecialchars(number_format($product['price'], 2)) ?></p>
                    <form action="cart.php" method="POST">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                        <button type="submit" name="add_to_cart" class="btn-glass-primary w-full">
                            <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php portal_footer(); ?>