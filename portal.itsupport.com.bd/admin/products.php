<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();
$message = '';

// Handle add/edit product action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_product']) || isset($_POST['edit_product']))) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $max_devices = (int)($_POST['max_devices'] ?? 1);
    $license_duration_days = (int)($_POST['license_duration_days'] ?? 365);
    $category = trim($_POST['category'] ?? 'AMPNM'); // NEW: Get category

    if (empty($name) || $price <= 0 || $max_devices <= 0 || $license_duration_days <= 0) {
        $message = '<div class="alert-admin-error mb-4">All fields are required and must be positive values.</div>';
    } else {
        try {
            if ($product_id > 0) { // Edit existing product
                $stmt = $pdo->prepare("UPDATE `products` SET name = ?, description = ?, price = ?, max_devices = ?, license_duration_days = ?, category = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$name, $description, $price, $max_devices, $license_duration_days, $category, $product_id]);
                $message = '<div class="alert-admin-success mb-4">Product updated successfully.</div>';
            } else { // Add new product
                $stmt = $pdo->prepare("INSERT INTO `products` (name, description, price, max_devices, license_duration_days, category) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $max_devices, $license_duration_days, $category]);
                $message = '<div class="alert-admin-success mb-4">Product added successfully.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert-admin-error mb-4">Error saving product: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Handle delete product action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `products` WHERE id = ?");
        $stmt->execute([$product_id]);
        $message = '<div class="alert-admin-success mb-4">Product deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error deleting product: ' . htmlspecialchars($e->getMessage()) . ' (Ensure no licenses are linked to this product)</div>';
    }
}

// Fetch all products
$stmt = $pdo->query("SELECT * FROM `products` ORDER BY name ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("Manage Products");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Manage Products</h1>

<?= $message ?>

<div class="admin-card mb-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Add New Product</h2>
    <form action="products.php" method="POST" class="space-y-4">
        <input type="hidden" name="product_id" value="0">
        <div>
            <label for="name" class="block text-gray-300 text-sm font-bold mb-2">Product Name:</label>
            <input type="text" id="name" name="name" class="form-admin-input" required>
        </div>
        <div>
            <label for="description" class="block text-gray-300 text-sm font-bold mb-2">Description:</label>
            <textarea id="description" name="description" class="form-admin-input"></textarea>
        </div>
        <div>
            <label for="category" class="block text-gray-300 text-sm font-bold mb-2">Category:</label>
            <select id="category" name="category" class="form-admin-input" required>
                <option value="AMPNM">AMPNM License</option>
                <option value="Other">Other Application License</option>
            </select>
        </div>
        <div>
            <label for="price" class="block text-gray-300 text-sm font-bold mb-2">Price ($):</label>
            <input type="number" id="price" name="price" step="0.01" min="0.01" class="form-admin-input" required>
        </div>
        <div>
            <label for="max_devices" class="block text-gray-300 text-sm font-bold mb-2">Max Devices:</label>
            <input type="number" id="max_devices" name="max_devices" min="1" class="form-admin-input" required>
        </div>
        <div>
            <label for="license_duration_days" class="block text-gray-300 text-sm font-bold mb-2">License Duration (Days):</label>
            <input type="number" id="license_duration_days" name="license_duration_days" min="1" class="form-admin-input" required value="365">
        </div>
        <button type="submit" name="add_product" class="btn-admin-primary">
            <i class="fas fa-plus-circle mr-1"></i>Add Product
        </button>
    </form>
</div>

<div class="admin-card p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">All Products</h2>
    <?php if (empty($products)): ?>
        <p class="text-center text-gray-400 py-8">No products defined yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-700 rounded-lg">
                <thead>
                    <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">ID</th>
                        <th class="py-3 px-6 text-left">Name</th>
                        <th class="py-3 px-6 text-left">Category</th>
                        <th class="py-3 px-6 text-left">Price</th>
                        <th class="py-3 px-6 text-left">Max Devices</th>
                        <th class="py-3 px-6 text-left">Duration (Days)</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-300 text-sm font-light">
                    <?php foreach ($products as $product): ?>
                        <tr class="border-b border-gray-600 hover:bg-gray-600">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?= htmlspecialchars($product['id']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['name']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                            <td class="py-3 px-6 text-left">$<?= htmlspecialchars(number_format($product['price'], 2)) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['max_devices']) ?></td>
                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($product['license_duration_days']) ?></td>
                            <td class="py-3 px-6 text-center">
                                <button onclick="openEditProductModal(<?= htmlspecialchars(json_encode($product)) ?>)" class="btn-admin-primary text-xs px-3 py-1 mr-2">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <form action="products.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this product? This will fail if licenses are linked.');" class="inline-block">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                    <button type="submit" name="delete_product" class="btn-admin-danger text-xs px-3 py-1">
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

<!-- Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden">
    <div class="bg-gray-700 p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-blue-400 mb-4">Edit Product</h2>
        <form action="products.php" method="POST" class="space-y-4">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div>
                <label for="edit_name" class="block text-gray-300 text-sm font-bold mb-2">Product Name:</label>
                <input type="text" id="edit_name" name="name" class="form-admin-input" required>
            </div>
            <div>
                <label for="edit_description" class="block text-gray-300 text-sm font-bold mb-2">Description:</label>
                <textarea id="edit_description" name="description" class="form-admin-input"></textarea>
            </div>
            <div>
                <label for="edit_category" class="block text-gray-300 text-sm font-bold mb-2">Category:</label>
                <select id="edit_category" name="category" class="form-admin-input" required>
                    <option value="AMPNM">AMPNM License</option>
                    <option value="Other">Other Application License</option>
                </select>
            </div>
            <div>
                <label for="edit_price" class="block text-gray-300 text-sm font-bold mb-2">Price ($):</label>
                <input type="number" id="edit_price" name="price" step="0.01" min="0.01" class="form-admin-input" required>
            </div>
            <div>
                <label for="edit_max_devices" class="block text-gray-300 text-sm font-bold mb-2">Max Devices:</label>
                <input type="number" id="edit_max_devices" name="max_devices" min="1" class="form-admin-input" required>
            </div>
            <div>
                <label for="edit_license_duration_days" class="block text-gray-300 text-sm font-bold mb-2">License Duration (Days):</label>
                <input type="number" id="edit_license_duration_days" name="license_duration_days" min="1" class="form-admin-input" required>
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditProductModal()" class="btn-admin-secondary">Cancel</button>
                <button type="submit" name="edit_product" class="btn-admin-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditProductModal(product) {
        document.getElementById('edit_product_id').value = product.id;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_description').value = product.description;
        document.getElementById('edit_category').value = product.category || 'AMPNM'; // Set category
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_max_devices').value = product.max_devices;
        document.getElementById('edit_license_duration_days').value = product.license_duration_days;
        document.getElementById('editProductModal').classList.remove('hidden');
    }

    function closeEditProductModal() {
        document.getElementById('editProductModal').classList.add('hidden');
    }
</script>

<?php admin_footer(); ?>