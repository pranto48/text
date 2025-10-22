<?php
require_once '../includes/functions.php';

// Ensure admin is logged in
if (!isAdminLoggedIn()) {
    redirectToAdminLogin();
}

$pdo = getLicenseDbConnection();
$message = '';

// Handle generate license action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['generate_license']))) {
    $customer_id = (int)$_POST['customer_id'];
    $product_id = (int)$_POST['product_id'];
    $status = $_POST['status'] ?? 'active';

    try {
        // Fetch product details to get max_devices and duration
        $stmt = $pdo->prepare("SELECT max_devices, license_duration_days FROM `products` WHERE id = ?");
        $stmt->execute([$product_id]);
        $product_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product_details) {
            throw new Exception("Product details not found for ID: " . $product_id);
        }

        $max_devices = $product_details['max_devices'];
        $license_duration_days = $product_details['license_duration_days'];
        $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));

        $license_key = generateLicenseKey();
        // Note: bound_installation_id is NULL by default on generation
        $stmt = $pdo->prepare("INSERT INTO `licenses` (customer_id, product_id, license_key, status, max_devices, expires_at, last_active_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"); // Set last_active_at on generation
        $stmt->execute([$customer_id, $product_id, $license_key, $status, $max_devices, $expires_at]);
        $message = '<div class="alert-admin-success mb-4">License generated successfully: ' . htmlspecialchars($license_key) . '</div>';
    } catch (Exception $e) {
        $message = '<div class="alert-admin-error mb-4">Error generating license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle update license status/expiry/max_devices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_license'])) {
    $license_id = (int)$_POST['license_id'];
    $new_status = $_POST['new_status'] ?? 'active';
    $new_expires_at = $_POST['new_expires_at'] ?? null;
    $new_max_devices = (int)$_POST['new_max_devices'];

    try {
        $sql = "UPDATE `licenses` SET status = ?, expires_at = ?, max_devices = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_status, $new_expires_at, $new_max_devices, $license_id]);
        $message = '<div class="alert-admin-success mb-4">License updated successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error updating license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle release license action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_license'])) {
    $license_id = (int)$_POST['license_id'];
    try {
        $stmt = $pdo->prepare("UPDATE `licenses` SET bound_installation_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$license_id]);
        $message = '<div class="alert-admin-success mb-4">License released successfully. It can now be used on a new server.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error releasing license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle delete license action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_license'])) {
    $license_id = (int)$_POST['license_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `licenses` WHERE id = ?");
        $stmt->execute([$license_id]);
        $message = '<div class="alert-admin-success mb-4">License deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert-admin-error mb-4">Error deleting license: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all customers for dropdown
$stmt_customers = $pdo->query("SELECT id, email FROM `customers` ORDER BY email ASC");
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products for dropdown
$stmt_products = $pdo->query("SELECT id, name FROM `products` ORDER BY name ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

admin_header("Manage Licenses");
?>

<h1 class="text-4xl font-bold text-blue-400 mb-8 text-center">Manage Licenses</h1>

<?= $message ?>

<div class="admin-card mb-8 p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">Generate New License</h2>
    <form action="license-manager.php" method="POST" class="space-y-4">
        <div>
            <label for="customer_id" class="block text-gray-300 text-sm font-bold mb-2">Assign to Customer:</label>
            <select id="customer_id" name="customer_id" class="form-admin-input" required>
                <option value="">-- Select Customer --</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= htmlspecialchars($customer['id']) ?>"><?= htmlspecialchars($customer['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="product_id" class="block text-gray-300 text-sm font-bold mb-2">Product Type:</label>
            <select id="product_id" name="product_id" class="form-admin-input" required>
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= htmlspecialchars($product['id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status" class="block text-gray-300 text-sm font-bold mb-2">Initial Status:</label>
            <select id="status" name="status" class="form-admin-input">
                <option value="active">Active</option>
                <option value="free">Free</option>
                <option value="expired">Expired</option>
                <option value="revoked">Revoked</option>
            </select>
        </div>
        <button type="submit" name="generate_license" class="btn-admin-primary">
            <i class="fas fa-plus-circle mr-1"></i>Generate License
        </button>
    </form>
</div>

<div class="admin-card p-6">
    <h2 class="text-2xl font-semibold text-blue-400 mb-4">All Licenses</h2>
    <div class="flex items-center gap-4 mb-4">
        <input type="text" id="licenseSearchInput" placeholder="Search by key or customer email..." class="form-admin-input flex-grow">
        <button id="clearSearchBtn" class="btn-admin-secondary text-xs px-3 py-1">Clear</button>
        <button id="refreshLicensesBtn" class="btn-admin-primary text-xs px-3 py-1"><i class="fas fa-sync-alt mr-1"></i> Refresh</button>
    </div>
    <div id="licensesTableContainer">
        <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-700 rounded-lg">
                <thead>
                    <tr class="bg-gray-600 text-gray-200 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Key</th>
                        <th class="py-3 px-6 text-left">Customer</th>
                        <th class="py-3 px-6 text-left">Product</th>
                        <th class="py-3 px-6 text-left">Status</th>
                        <th class="py-3 px-6 text-left">Max Devices</th>
                        <th class="py-3 px-6 text-left">Current Devices</th>
                        <th class="py-3 px-6 text-left">Last Active</th>
                        <th class="py-3 px-6 text-left">Expires At</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="licensesTableBody" class="text-gray-300 text-sm font-light">
                    <!-- Licenses will be loaded here by JavaScript -->
                    <tr><td colspan="9" class="text-center py-4">Loading licenses...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit License Modal -->
<div id="editLicenseModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden">
    <div class="bg-gray-700 p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-blue-400 mb-4">Edit License</h2>
        <form action="license-manager.php" method="POST" class="space-y-4">
            <input type="hidden" name="license_id" id="edit_license_id">
            <div>
                <label for="edit_license_key" class="block text-gray-300 text-sm font-bold mb-2">License Key:</label>
                <input type="text" id="edit_license_key" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="edit_customer_email" class="block text-gray-300 text-sm font-bold mb-2">Customer Email:</label>
                <input type="text" id="edit_customer_email" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="edit_product_name" class="block text-gray-300 text-sm font-bold mb-2">Product:</label>
                <input type="text" id="edit_product_name" class="form-admin-input" readonly>
            </div>
            <div>
                <label for="new_status" class="block text-gray-300 text-sm font-bold mb-2">Status:</label>
                <select id="new_status" name="new_status" class="form-admin-input">
                    <option value="active">Active</option>
                    <option value="free">Free</option>
                    <option value="expired">Expired</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            <div>
                <label for="new_max_devices" class="block text-gray-300 text-sm font-bold mb-2">Max Devices:</label>
                <input type="number" id="new_max_devices" name="new_max_devices" class="form-admin-input" required>
            </div>
            <div>
                <label for="new_expires_at" class="block text-gray-300 text-sm font-bold mb-2">Expires At:</label>
                <input type="date" id="new_expires_at" name="new_expires_at" class="form-admin-input">
            </div>
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditLicenseModal()" class="btn-admin-secondary">Cancel</button>
                <button type="submit" name="update_license" class="btn-admin-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
    // Initialize Notyf for toast notifications
    const notyf = new Notyf({
        duration: 3000,
        position: { x: 'right', y: 'top' },
        types: [
            { type: 'success', backgroundColor: '#22c5e', icon: { className: 'fas fa-check-circle', tagName: 'i', color: 'white' } },
            { type: 'error', backgroundColor: '#ef4444', icon: { className: 'fas fa-times-circle', tagName: 'i', color: 'white' } },
            { type: 'info', backgroundColor: '#3b82f6', icon: { className: 'fas fa-info-circle', tagName: 'i', color: 'white' } }
        ]
    });

    function openEditLicenseModal(license) {
        document.getElementById('edit_license_id').value = license.id;
        document.getElementById('edit_license_key').value = license.license_key;
        document.getElementById('edit_customer_email').value = license.customer_email || 'N/A';
        document.getElementById('edit_product_name').value = license.product_name || 'N/A';
        document.getElementById('new_status').value = license.status;
        document.getElementById('new_max_devices').value = license.max_devices;
        document.getElementById('new_expires_at').value = license.expires_at ? license.expires_at.split(' ')[0] : ''; // Extract date part
        document.getElementById('editLicenseModal').classList.remove('hidden');
    }

    function closeEditLicenseModal() {
        document.getElementById('editLicenseModal').classList.add('hidden');
    }

    const licenseSearchInput = document.getElementById('licenseSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const refreshLicensesBtn = document.getElementById('refreshLicensesBtn');
    let searchTimeout;

    // Function to fetch and render licenses
    async function fetchAndRenderLicenses(searchTerm = '') {
        try {
            const licensesTableBody = document.getElementById('licensesTableBody');
            licensesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="loader mx-auto"></div></td></tr>'; // Show loader

            const response = await fetch(`admin_api.php?action=get_all_licenses&search=${encodeURIComponent(searchTerm)}`);
            const data = await response.json();

            if (data.success && data.licenses) {
                licensesTableBody.innerHTML = ''; // Clear existing rows

                if (data.licenses.length === 0) {
                    licensesTableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4">No licenses found.</td></tr>';
                } else {
                    data.licenses.forEach(license => {
                        const row = `
                            <tr class="border-b border-gray-600 hover:bg-gray-600">
                                <td class="py-3 px-6 text-left font-mono break-all">${license.license_key}</td>
                                <td class="py-3 px-6 text-left">${license.customer_email || 'N/A'}</td>
                                <td class="py-3 px-6 text-left">${license.product_name || 'N/A'}</td>
                                <td class="py-3 px-6 text-left">
                                    <span class="py-1 px-3 rounded-full text-xs ${license.status == 'active' || license.status == 'free' ? 'bg-green-500' : (license.status == 'expired' ? 'bg-red-500' : 'bg-yellow-500')}">
                                        ${license.status.charAt(0).toUpperCase() + license.status.slice(1)}
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-left">${license.max_devices}</td>
                                <td class="py-3 px-6 text-left">${license.current_devices}</td>
                                <td class="py-3 px-6 text-left">${license.last_active_at ? new Date(license.last_active_at).toLocaleString() : 'Never'}</td>
                                <td class="py-3 px-6 text-left">${license.expires_at ? new Date(license.expires_at).toLocaleDateString() : 'Never'}</td>
                                <td class="py-3 px-6 text-center">
                                    <button onclick="openEditLicenseModal(${JSON.stringify(license)})" class="btn-admin-primary text-xs px-3 py-1 mr-2">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    ${license.bound_installation_id ? `
                                        <form action="license-manager.php" method="POST" onsubmit="return confirm('Are you sure you want to release this license? This will unbind it from the current server, allowing it to be used on a new one.');" class="inline-block">
                                            <input type="hidden" name="license_id" value="${license.id}">
                                            <button type="submit" name="release_license" class="btn-admin-secondary text-xs px-3 py-1 mr-2" title="Unbind from current server">
                                                <i class="fas fa-unlink mr-1"></i>Release
                                            </button>
                                        </form>
                                    ` : ''}
                                    <form action="license-manager.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this license?');" class="inline-block">
                                        <input type="hidden" name="license_id" value="${license.id}">
                                        <button type="submit" name="delete_license" class="btn-admin-danger text-xs px-3 py-1">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        `;
                        licensesTableBody.insertAdjacentHTML('beforeend', row);
                    });
                }
            } else {
                notyf.error(data.error || 'Failed to load licenses.');
            }
        } catch (error) {
            console.error('Error fetching licenses:', error);
            notyf.error('An error occurred while fetching licenses.');
        }
    }

    licenseSearchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchAndRenderLicenses(licenseSearchInput.value);
        }, 500); // Debounce for 500ms
    });

    clearSearchBtn.addEventListener('click', () => {
        licenseSearchInput.value = '';
        fetchAndRenderLicenses('');
    });

    refreshLicensesBtn.addEventListener('click', () => {
        fetchAndRenderLicenses(licenseSearchInput.value);
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Display server-side messages as Notyf toasts
        const messageDiv = document.querySelector('.alert-admin-success, .alert-admin-error');
        if (messageDiv) {
            const messageText = messageDiv.textContent.trim();
            if (messageDiv.classList.contains('alert-admin-success')) {
                notyf.success(messageText);
            } else if (messageDiv.classList.contains('alert-admin-error')) {
                notyf.error(messageText);
            }
            messageDiv.style.display = 'none'; // Hide the original PHP message
        }

        // Initial load of licenses
        fetchAndRenderLicenses();

        // Set up polling for real-time updates (every 10 seconds) - now also triggers search
        setInterval(() => fetchAndRenderLicenses(licenseSearchInput.value), 10000);
    });
</script>

<?php admin_footer(); ?>