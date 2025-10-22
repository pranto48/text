<?php
require_once 'includes/functions.php';

$pdo = getLicenseDbConnection();
$stmt = $pdo->query("SELECT * FROM `products` ORDER BY category ASC, price ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group products by category
$products_by_category = [];
foreach ($products as $product) {
    $category = $product['category'] ?? 'Other'; // Default to 'Other' if category is null
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $product;
}

portal_header("Our Products - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-white mb-8 text-center">Our AMPNM License Products</h1>

<?php if (empty($products_by_category)): ?>
    <p class="text-center text-gray-200 col-span-full">No products available at the moment. Please check back later!</p>
<?php else: ?>
    <?php foreach ($products_by_category as $category => $category_products): ?>
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-blue-300 mb-6 text-center"><?= htmlspecialchars($category) ?></h2>
            <?php if ($category === 'AMPNM'): ?>
                <div class="glass-card p-6 max-w-md mx-auto">
                    <h3 class="text-2xl font-semibold text-white mb-4">Select Your AMPNM License</h3>
                    <form action="cart.php" method="POST" class="space-y-4">
                        <div>
                            <label for="ampnm_product_select" class="block text-gray-200 text-sm font-bold mb-2">Choose License:</label>
                            <select id="ampnm_product_select" name="product_id" class="form-glass-input" required>
                                <?php foreach ($category_products as $product): ?>
                                    <option value="<?= htmlspecialchars($product['id']) ?>">
                                        <?= htmlspecialchars($product['name']) ?> - $<?= htmlspecialchars(number_format($product['price'], 2)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn-glass-primary w-full">
                            <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($category_products as $product): ?>
                        <div class="glass-card flex flex-col justify-between p-6">
                            <div>
                                <h3 class="text-2xl font-semibold text-white mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="text-gray-200 mb-4"><?= htmlspecialchars($product['description']) ?></p>
                                <ul class="list-disc list-inside text-gray-100 mb-4 pl-4">
                                    <li>Max Devices: <?= $product['max_devices'] == 99999 ? 'Unlimited' : htmlspecialchars($product['max_devices']) ?></li>
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
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php portal_footer(); ?>