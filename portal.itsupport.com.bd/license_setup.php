<?php
session_start(); // Start session to manage steps

$setup_message = '';
$config_file_path = __DIR__ . '/config.php';

// Helper function to check if a column exists (needed for migrations)
function columnExists($pdo, $db, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    // NOTE: LICENSE_DB_NAME is not defined yet if config.php hasn't been created. We must rely on the connection's current DB name.
    // If config is loaded, use LICENSE_DB_NAME, otherwise use a placeholder.
    $db_name = defined('LICENSE_DB_NAME') ? LICENSE_DB_NAME : 'license_db';
    
    $stmt->execute([$db_name, $table, $column]);
    return $stmt->fetchColumn() > 0;
}


// Function to update config.php with new DB credentials
function updateConfigFile($db_server, $db_name, $db_username, $db_password) {
    global $config_file_path;
    $content = <<<EOT
<?php
// External License Service Database Configuration
define('LICENSE_DB_SERVER', '{$db_server}');
define('LICENSE_DB_USERNAME', '{$db_username}');
define('LICENSE_DB_PASSWORD', '{$db_password}');
define('LICENSE_DB_NAME', '{$db_name}');

// Function to create database connection for the license service
function getLicenseDbConnection() {
    static \$pdo = null;

    if (\$pdo !== null) {
        try {
            \$pdo->query("SELECT 1");
        } catch (PDOException \$e) {
            if (isset(\$e->errorInfo[1]) && \$e->errorInfo[1] == 2006) {
                \$pdo = null;
            } else {
                throw \$e;
            }
        }
    }

    if (\$pdo === null) {
        try {
            \$dsn = "mysql:host=" . LICENSE_DB_SERVER . ";dbname=" . LICENSE_DB_NAME . ";charset=utf8mb4";
            \$options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            \$pdo = new PDO(\$dsn, LICENSE_DB_USERNAME, LICENSE_DB_PASSWORD, \$options);
        } catch(PDOException \$e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the license database. " . \$e->getMessage());
        }
    }
    
    return \$pdo;
}
EOT;
    return file_put_contents($config_file_path, $content);
}

// Helper to check if config is present and valid
function isConfiguredAndDbConnects($config_file_path) {
    if (!file_exists($config_file_path)) return false;
    require_once $config_file_path;
    if (!defined('LICENSE_DB_SERVER') || !defined('LICENSE_DB_NAME')) return false;
    try {
        getLicenseDbConnection(); // Attempt connection
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Helper to check if admin_users table exists and has an admin
function isAdminUserSetup($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM `admin_users` LIMIT 1");
        // Table exists, check for admin user
        $stmt = $pdo->prepare("SELECT id FROM `admin_users` WHERE username = 'admin'"); // Assuming 'admin' is the default
        $stmt->execute();
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        // Table does not exist or other error
        return false;
    }
}

// Helper to check if all other tables exist (products, customers, orders, order_items, licenses)
function areAllTablesSetup($pdo) {
    $tables_to_check = ['products', 'customers', 'orders', 'order_items', 'licenses', 'support_tickets', 'ticket_replies', 'profiles']; // Added 'profiles'
    foreach ($tables_to_check as $table) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        } catch (PDOException $e) {
            return false; // Table doesn't exist
        }
    }
    return true;
}

// Determine current step
$step = 1;
if (isConfiguredAndDbConnects($config_file_path)) {
    require_once $config_file_path; // Ensure config is loaded for getLicenseDbConnection
    $pdo = getLicenseDbConnection();
    if (isAdminUserSetup($pdo)) {
        if (areAllTablesSetup($pdo)) {
            $step = 4; // All done
        } else {
            $step = 3; // Admin exists, but other tables are missing
        }
    } else {
        $step = 2; // DB configured, but admin user is missing
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'configure_db' && $step === 1) {
            $db_server = $_POST['db_server'] ?? '';
            $db_name = $_POST['db_name'] ?? '';
            $db_username = $_POST['db_username'] ?? '';
            $db_password = $_POST['db_password'] ?? '';

            if (empty($db_server) || empty($db_name) || empty($db_username)) {
                $setup_message = '<p class="text-red-500">All database fields except password are required.</p>';
            } else {
                try {
                    // Attempt to connect to MySQL server (without selecting a database)
                    $pdo_root = new PDO("mysql:host=$db_server", $db_username, $db_password);
                    $pdo_root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Create database if it doesn't exist
                    $pdo_root->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}`");
                    $setup_message .= '<p class="text-green-500">Database ' . htmlspecialchars($db_name) . ' checked/created successfully.</p>';

                    // Update config.php
                    if (updateConfigFile($db_server, $db_name, $db_username, $db_password)) {
                        $setup_message .= '<p class="text-green-500">Configuration saved to config.php.</p>';
                        $step = 2; // Move to next step
                        // Reload config to use new settings for subsequent checks
                        require_once $config_file_path;
                    } else {
                        $setup_message .= '<p class="text-red-500">Failed to write to config.php. Check file permissions.</p>';
                    }

                } catch (PDOException $e) {
                    $setup_message .= '<p class="text-red-500">Database connection or creation failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        } elseif ($_POST['action'] === 'setup_admin' && $step === 2) {
            $admin_username = trim($_POST['admin_username'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';

            if (empty($admin_username) || empty($admin_password)) {
                $setup_message = '<p class="text-red-500">Admin username and password are required.</p>';
            } else {
                try {
                    require_once $config_file_path; // Ensure config is loaded
                    $pdo = getLicenseDbConnection();

                    // Create admin_users table if it doesn't exist
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
                        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `username` VARCHAR(255) NOT NULL UNIQUE,
                        `password` VARCHAR(255) NOT NULL,
                        `email` VARCHAR(255) NOT NULL UNIQUE,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                    $setup_message .= '<p class="text-green-500">Table `admin_users` checked/created successfully.</p>';

                    // Insert or update admin user
                    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                    $admin_email = $admin_username . '@portal.itsupport.com.bd'; // Default email

                    $stmt = $pdo->prepare("SELECT id FROM `admin_users` WHERE username = ?");
                    $stmt->execute([$admin_username]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO `admin_users` (username, password, email) VALUES (?, ?, ?)");
                        $stmt->execute([$admin_username, $hashed_password, $admin_email]);
                        $setup_message .= '<p class="text-green-500">Admin user ' . htmlspecialchars($admin_username) . ' created.</p>';
                    } else {
                        $stmt = $pdo->prepare("UPDATE `admin_users` SET password = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
                        $stmt->execute([$hashed_password, $admin_email, $admin_username]);
                        $setup_message .= '<p class="text-orange-500">Admin user ' . htmlspecialchars($admin_username) . ' password updated.</p>';
                    }
                    $step = 3; // Move to next step
                } catch (PDOException $e) {
                    $setup_message .= '<p class="text-red-500">Admin user setup failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        } elseif ($_POST['action'] === 'setup_tables' && $step === 3) {
            try {
                require_once $config_file_path; // Ensure config is loaded
                $pdo = getLicenseDbConnection();
                $db_name = LICENSE_DB_NAME; // Get DB name for migration checks

                // Create products table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `description` TEXT,
                    `price` DECIMAL(10, 2) NOT NULL,
                    `max_devices` INT(11) DEFAULT 1,
                    `license_duration_days` INT(11) DEFAULT 365, -- e.g., 365 for 1 year
                    `category` VARCHAR(100) DEFAULT 'AMPNM', -- NEW COLUMN
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `products` checked/created successfully.</p>';

                // Migration: Add category column if it doesn't exist
                if (!columnExists($pdo, $db_name, 'products', 'category')) {
                    $pdo->exec("ALTER TABLE `products` ADD COLUMN `category` VARCHAR(100) DEFAULT 'AMPNM' AFTER `license_duration_days`;");
                    $setup_message .= '<p class="text-green-500">Migrated `products` table: added `category` column.</p>';
                }


                // Create customers table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(255) NOT NULL UNIQUE,
                    `password` VARCHAR(255) NOT NULL,
                    `first_name` VARCHAR(255),
                    `last_name` VARCHAR(255),
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `customers` checked/created successfully.</p>';

                // Create licenses table (depends on customers and products)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `licenses` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT(11) UNSIGNED NULL,
                    `product_id` INT(11) UNSIGNED NULL,
                    `license_key` VARCHAR(255) NOT NULL UNIQUE,
                    `status` ENUM('active', 'expired', 'revoked', 'free') DEFAULT 'active',
                    `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `expires_at` TIMESTAMP NULL,
                    `max_devices` INT(11) DEFAULT 1,
                    `current_devices` INT(11) DEFAULT 0,
                    `last_active_at` TIMESTAMP NULL, -- New column for last check-in
                    `bound_installation_id` VARCHAR(255) NULL, -- NEW: To track which AMPNM instance is using it
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `licenses` checked/created successfully.</p>';

                // Create orders table (depends on customers)
                // Updated ENUM and added payment columns
                $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT(11) UNSIGNED NOT NULL,
                    `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `total_amount` DECIMAL(10, 2) NOT NULL,
                    `status` ENUM('pending', 'pending_approval', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                    `payment_intent_id` VARCHAR(255) NULL,
                    `payment_method` VARCHAR(50) NULL,
                    `transaction_id` VARCHAR(255) NULL,
                    `sender_number` VARCHAR(50) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `orders` checked/created successfully.</p>';

                // Migration checks for new payment columns (in case the user ran setup before this update)
                if (!columnExists($pdo, $db_name, 'orders', 'payment_method')) {
                    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) NULL AFTER `status`;");
                    $setup_message .= '<p class="text-green-500">Migrated `orders` table: added `payment_method` column.</p>';
                }
                if (!columnExists($pdo, $db_name, 'orders', 'transaction_id')) {
                    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `transaction_id` VARCHAR(255) NULL AFTER `payment_method`;");
                    $setup_message .= '<p class="text-green-500">Migrated `orders` table: added `transaction_id` column.</p>';
                }
                if (!columnExists($pdo, $db_name, 'orders', 'sender_number')) {
                    $pdo->exec("ALTER TABLE `orders` ADD COLUMN `sender_number` VARCHAR(50) NULL AFTER `transaction_id`;");
                    $setup_message .= '<p class="text-green-500">Migrated `orders` table: added `sender_number` column.</p>';
                }
                // Migration for ENUM status (complex, but necessary if the table existed without 'pending_approval')
                // This is a simplified migration that might fail on some MySQL versions, but covers the intent.
                try {
                    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'pending_approval', 'completed', 'failed', 'refunded') DEFAULT 'pending';");
                    $setup_message .= '<p class="text-green-500">Migrated `orders` table: updated `status` ENUM.</p>';
                } catch (PDOException $e) {
                    $setup_message .= '<p class="text-orange-500">Warning: Could not automatically update `orders` status ENUM. Manual update may be required if "pending_approval" is missing.</p>';
                }


                // Create order_items table (depends on orders and products)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `order_id` INT(11) UNSIGNED NOT NULL,
                    `product_id` INT(11) UNSIGNED NOT NULL,
                    `quantity` INT(11) NOT NULL DEFAULT 1,
                    `price` DECIMAL(10, 2) NOT NULL,
                    `license_key_generated` VARCHAR(255) NULL, -- Store the generated license key here
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `order_items` checked/created successfully.</p>';

                // NEW TABLES FOR SUPPORT TICKET SYSTEM
                $pdo->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT(11) UNSIGNED NOT NULL,
                    `subject` VARCHAR(255) NOT NULL,
                    `message` TEXT NOT NULL,
                    `status` ENUM('open', 'in progress', 'closed') DEFAULT 'open',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `support_tickets` checked/created successfully.</p>';

                $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_replies` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `ticket_id` INT(11) UNSIGNED NOT NULL,
                    `sender_id` INT(11) UNSIGNED NOT NULL,
                    `sender_type` ENUM('customer', 'admin') NOT NULL,
                    `message` TEXT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `ticket_replies` checked/created successfully.</p>';

                // NEW TABLE FOR USER PROFILES
                $pdo->exec("CREATE TABLE IF NOT EXISTS `profiles` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT(11) UNSIGNED NOT NULL UNIQUE,
                    `avatar_url` VARCHAR(255) NULL,
                    `address` VARCHAR(255) NULL,
                    `phone` VARCHAR(50) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $setup_message .= '<p class="text-green-500">Table `profiles` checked/created successfully.</p>';


                // Insert some sample products if they don't exist
                $sample_products = [
                    ['name' => 'AMPNM Free License (10 Devices / 1 Year)', 'description' => 'Free license for up to 10 devices, valid for 1 year.', 'price' => 0.00, 'max_devices' => 10, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                    ['name' => 'AMPNM Basic License (15 Devices / 1 Year)', 'description' => 'Basic license for up to 15 devices, valid for 1 year.', 'price' => 1.00, 'max_devices' => 15, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                    ['name' => 'AMPNM Standard License (20 Devices / 1 Year)', 'description' => 'Standard license for up to 20 devices, valid for 1 year.', 'price' => 5.00, 'max_devices' => 20, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                    ['name' => 'AMPNM Advance License (30 Devices / 1 Year)', 'description' => 'Advance license for up to 30 devices, valid for 1 year.', 'price' => 10.00, 'max_devices' => 30, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                    ['name' => 'AMPNM Premium License (50 Devices / 1 Year)', 'description' => 'Premium license for up to 50 devices, valid for 1 year.', 'price' => 50.00, 'max_devices' => 50, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                    ['name' => 'AMPNM Ultimate License (Unlimited Devices / 1 Year)', 'description' => 'Ultimate license for unlimited devices, valid for 1 year.', 'price' => 100.00, 'max_devices' => 99999, 'license_duration_days' => 365, 'category' => 'AMPNM'],
                ];

                foreach ($sample_products as $product_data) {
                    $stmt = $pdo->prepare("SELECT id FROM `products` WHERE name = ?");
                    $stmt->execute([$product_data['name']]);
                    if (!$stmt->fetch()) {
                        $sql = "INSERT INTO `products` (name, description, price, max_devices, license_duration_days, category) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$product_data['name'], $product_data['description'], $product_data['price'], $product_data['max_devices'], $product_data['license_duration_days'], $product_data['category']]);
                        $setup_message .= '<p class="text-green-500">Added sample product: ' . htmlspecialchars($product_data['name']) . '</p>';
                    } else {
                        // Update existing product if it matches by name
                        $sql = "UPDATE `products` SET description = ?, price = ?, max_devices = ?, license_duration_days = ?, category = ?, updated_at = CURRENT_TIMESTAMP WHERE name = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$product_data['description'], $product_data['price'], $product_data['max_devices'], $product_data['license_duration_days'], $product_data['category'], $product_data['name']]);
                        $setup_message .= '<p class="text-orange-500">Updated existing product: ' . htmlspecialchars($product_data['name']) . '</p>';
                    }
                }

                // Migration: Add last_active_at if it doesn't exist
                if (!columnExists($pdo, $db_name, 'licenses', 'last_active_at')) {
                    $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `last_active_at` TIMESTAMP NULL AFTER `current_devices`;");
                    $setup_message .= '<p class="text-green-500">Migrated `licenses` table: added `last_active_at` column.</p>';
                }
                // NEW MIGRATION: Add bound_installation_id if it doesn't exist
                if (!columnExists($pdo, $db_name, 'licenses', 'bound_installation_id')) {
                    $pdo->exec("ALTER TABLE `licenses` ADD COLUMN `bound_installation_id` VARCHAR(255) NULL AFTER `last_active_at`;");
                    $setup_message .= '<p class="text-green-500">Migrated `licenses` table: added `bound_installation_id` column.</p>';
                }


                $setup_message .= '<p class="text-blue-500">Database setup for license service completed!</p>';
                $step = 4; // Move to final step

            } catch (PDOException $e) {
                $setup_message .= '<p class="text-red-500">Table creation or license insertion failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Service Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e0e0e0;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        .form-input {
            @apply w-full py-2 px-4 rounded-lg text-white placeholder-gray-300;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }
        .form-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.5);
        }
        .btn-primary {
            @apply bg-blue-500 text-white font-semibold py-2 px-6 rounded-full shadow-lg;
            background: linear-gradient(45deg, #4a90e2, #50e3c2);
            transition: all 0.3s ease-in-out;
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            opacity: 0.9;
        }
        .loader { border: 4px solid #334155; border-top: 4px solid #22d3ee; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1 class="text-3xl font-bold text-white mb-6 text-center">License Service Setup</h1>
        
        <div class="mb-6 text-center">
            <?php if ($step === 1): ?>
                <span class="inline-block px-4 py-2 rounded-full bg-blue-500 text-white font-semibold">Step 1 of 3: Database Configuration</span>
            <?php elseif ($step === 2): ?>
                <span class="inline-block px-4 py-2 rounded-full bg-blue-500 text-white font-semibold">Step 2 of 3: Admin User Setup</span>
            <?php elseif ($step === 3): ?>
                <span class="inline-block px-4 py-2 rounded-full bg-blue-500 text-white font-semibold">Step 3 of 3: Finalizing Tables</span>
            <?php elseif ($step === 4): ?>
                <span class="inline-block px-4 py-2 rounded-full bg-green-500 text-white font-semibold">Setup Complete!</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($setup_message)): ?>
            <div class="bg-gray-800 p-4 rounded-lg mb-6 text-sm">
                <?= $setup_message ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="configure_db">
                <div>
                    <label for="db_server" class="block text-gray-200 text-sm font-bold mb-2">Database Host:</label>
                    <input type="text" id="db_server" name="db_server" class="form-input" value="localhost" required>
                </div>
                <div>
                    <label for="db_name" class="block text-gray-200 text-sm font-bold mb-2">Database Name:</label>
                    <input type="text" id="db_name" name="db_name" class="form-input" value="license_db" required>
                </div>
                <div>
                    <label for="db_username" class="block text-gray-200 text-sm font-bold mb-2">Database Username:</label>
                    <input type="text" id="db_username" name="db_username" class="form-input" value="root" required>
                </div>
                <div>
                    <label for="db_password" class="block text-gray-200 text-sm font-bold mb-2">Database Password:</label>
                    <input type="password" id="db_password" name="db_password" class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full">
                    <i class="fas fa-database mr-2"></i>Configure Database
                </button>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="setup_admin">
                <div>
                    <label for="admin_username" class="block text-gray-200 text-sm font-bold mb-2">Admin Username:</label>
                    <input type="text" id="admin_username" name="admin_username" class="form-input" value="admin" required>
                </div>
                <div>
                    <label for="admin_password" class="block text-gray-200 text-sm font-bold mb-2">Admin Password:</label>
                    <input type="password" id="admin_password" name="admin_password" class="form-input" required>
                </div>
                <button type="submit" class="btn-primary w-full">
                    <i class="fas fa-user-shield mr-2"></i>Setup Admin User
                </button>
            </form>
        <?php elseif ($step === 3): ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="setup_tables">
                <p class="text-gray-200 mb-4">Click "Finalize Installation" to create all remaining tables and add sample products.</p>
                <button type="submit" class="btn-primary w-full">
                    <i class="fas fa-check-circle mr-2"></i>Finalize Installation
                </button>
            </form>
        <?php elseif ($step === 4): ?>
            <div class="text-center space-y-4">
                <i class="fas fa-check-double text-6xl text-green-400 mb-4"></i>
                <h2 class="text-2xl font-bold text-white">Installation Complete!</h2>
                <p class="text-gray-200">Your License Portal is now ready.</p>
                <a href="adminpanel.php" class="btn-primary inline-block mt-4">
                    <i class="fas fa-user-shield mr-2"></i>Go to Admin Panel
                </a>
                <a href="index.php" class="btn-primary inline-block mt-4 ml-4">
                    <i class="fas fa-home mr-2"></i>Go to Portal Home
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>