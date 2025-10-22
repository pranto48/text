<?php
// Database configuration using environment variables for Docker compatibility
$servername = getenv('DB_HOST') ?: 'db'; // Use DB_HOST env var, fallback to 'db' service name
$root_username = 'root'; // Setup script needs root privileges to create DB and tables
$root_password = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$app_username = getenv('DB_USER') ?: 'user';
$app_password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'network_monitor';

// Load the main application's config.php for getDbConnection if needed later,
// but for initial setup, we use direct PDO connection.
// The functions.php is not needed here as DB connection is direct.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php'; // Include functions for generateUuid

function message($text, $is_error = false) {
    $color = $is_error ? '#ef4444' : '#22c55e';
    echo "<p style='color: $color; margin: 4px 0; font-family: monospace;'>$text</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Setup</title>
    <style>
        body { background-color: #0f172a; color: #cbd5e1; font-family: sans-serif; padding: 2rem; }
        .loader { border: 4px solid #334155; border-top: 4px solid #22d3ee; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
<?php
try {
    // Connect to MySQL server (without selecting a database) using root credentials
    $pdo = new PDO("mysql:host=$servername", $root_username, $root_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    message("Database '$dbname' checked/created successfully.");

    // Reconnect, this time selecting the new database and using the application user credentials
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $app_username, $app_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Step 1: Ensure users table exists first with 'role' column
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'user') DEFAULT 'user' NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Table 'users' checked/created successfully.");

    // Migration: Add 'role' column if it doesn't exist
    function columnExists($pdo, $db, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$db, $table, $column]);
        return $stmt->fetchColumn() > 0;
    }

    if (!columnExists($pdo, $dbname, 'users', 'role')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin', 'user') DEFAULT 'user' NOT NULL AFTER `password`;");
        message("Migrated 'users' table: added 'role' column.");
    }


    // Step 2: Ensure admin user exists and set password from environment variable
    $admin_user = 'admin';
    $admin_password = getenv('ADMIN_PASSWORD') ? getenv('ADMIN_PASSWORD') : 'password';
    $is_default_password = ($admin_password === 'password');

    $stmt = $pdo->prepare("SELECT id, password FROM `users` WHERE username = ?");
    $stmt->execute([$admin_user]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data) {
        $admin_pass_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO `users` (username, password, role) VALUES (?, ?, 'admin')")->execute([$admin_user, $admin_pass_hash]);
        $admin_id = $pdo->lastInsertId();
        message("Created default user 'admin' with 'admin' role.");
        if ($is_default_password) {
            message("WARNING: Admin password is set to the default 'password'. Please change the ADMIN_PASSWORD in docker-compose.yml for security.", true);
        } else {
            message("Admin password set securely from environment variable.");
        }
    } else {
        $admin_id = $admin_data['id'];
        // Update password if it's changed in the env var and doesn't match the current one
        if (!password_verify($admin_password, $admin_data['password'])) {
            $new_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE `users` SET password = ? WHERE id = ?");
            $updateStmt->execute([$new_hash, $admin_id]);
            message("Updated admin password from environment variable.");
        }
        // Ensure admin user has 'admin' role
        $stmt = $pdo->prepare("UPDATE `users` SET role = 'admin' WHERE id = ? AND role != 'admin'");
        $stmt->execute([$admin_id]);
        if ($stmt->rowCount() > 0) {
            message("Ensured 'admin' user has 'admin' role.");
        }
    }

    // Step 3: Create the rest of the tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS `ping_results` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `host` VARCHAR(100) NOT NULL,
            `packet_loss` INT(3) NOT NULL,
            `avg_time` DECIMAL(10,2) NOT NULL,
            `min_time` DECIMAL(10,2) NOT NULL,
            `max_time` DECIMAL(10,2) NOT NULL,
            `success` BOOLEAN NOT NULL,
            `output` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `maps` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `description` TEXT,
            `background_color` VARCHAR(20) NULL,
            `background_image_url` VARCHAR(255) NULL,
            `is_default` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `devices` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `ip` VARCHAR(15) NULL,
            `check_port` INT(5) NULL,
            `name` VARCHAR(100) NOT NULL,
            `status` ENUM('online', 'offline', 'unknown', 'warning', 'critical') DEFAULT 'unknown',
            `last_seen` TIMESTAMP NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'server',
            `description` TEXT,
            `enabled` BOOLEAN DEFAULT TRUE,
            `x` DECIMAL(10, 4) NULL,
            `y` DECIMAL(10, 4) NULL,
            `map_id` INT(6) UNSIGNED,
            `ping_interval` INT(11) NULL,
            `icon_size` INT(11) DEFAULT 50,
            `name_text_size` INT(11) DEFAULT 14,
            `icon_url` VARCHAR(255) NULL,
            `warning_latency_threshold` INT(11) NULL,
            `warning_packetloss_threshold` INT(11) NULL,
            `critical_latency_threshold` INT(11) NULL,
            `critical_packetloss_threshold` INT(11) NULL,
            `last_avg_time` DECIMAL(10, 2) NULL,
            `last_ttl` INT(11) NULL,
            `show_live_ping` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `device_edges` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `source_id` INT(6) UNSIGNED NOT NULL,
            `target_id` INT(6) UNSIGNED NOT NULL,
            `map_id` INT(6) UNSIGNED NOT NULL,
            `connection_type` VARCHAR(50) DEFAULT 'cat5',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`source_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`target_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `device_status_logs` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `status` ENUM('online', 'offline', 'unknown', 'warning', 'critical') NOT NULL,
            `details` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for SMTP settings
        "CREATE TABLE IF NOT EXISTS `smtp_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `host` VARCHAR(255) NOT NULL,
            `port` INT(5) NOT NULL,
            `username` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
            `from_email` VARCHAR(255) NOT NULL,
            `from_name` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for device email subscriptions
        "CREATE TABLE IF NOT EXISTS `device_email_subscriptions` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `recipient_email` VARCHAR(255) NOT NULL,
            `notify_on_online` BOOLEAN DEFAULT TRUE,
            `notify_on_offline` BOOLEAN DEFAULT TRUE,
            `notify_on_warning` BOOLEAN DEFAULT TRUE,
            `notify_on_critical` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `device_recipient_unique` (`device_id`, `recipient_email`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // NEW TABLE: app_settings for storing the application license key and installation ID
        "CREATE TABLE IF NOT EXISTS `app_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(255) NOT NULL UNIQUE,
            `setting_value` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $sql, $matches);
        $tableName = $matches[1] ? $matches[1] : 'unknown';
        message("Table '$tableName' checked/created successfully.");
    }

    // Step 4: Schema migration section to handle upgrades
    // User ID migrations for existing tables
    if (!columnExists($pdo, $dbname, 'maps', 'user_id')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `maps` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `maps` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `maps` ADD CONSTRAINT `fk_maps_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'maps' table: assigned existing maps to admin.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'user_id')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `devices` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `devices` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `devices` ADD CONSTRAINT `fk_devices_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'devices' table: assigned existing devices to admin.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'user_id')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `device_edges` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `device_edges` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `device_edges` ADD CONSTRAINT `fk_device_edges_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'device_edges' table: assigned existing edges to admin.");
    }

    // Device column migrations
    if (!columnExists($pdo, $dbname, 'devices', 'check_port')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `check_port` INT(5) NULL AFTER `ip`;");
        message("Upgraded 'devices' table: added 'check_port' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'description')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `description` TEXT NULL AFTER `type`;");
        message("Upgraded 'devices' table: added 'description' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'ping_interval')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `ping_interval` INT(11) NULL AFTER `map_id`;");
        message("Upgraded 'devices' table: added 'ping_interval' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'icon_size')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `icon_size` INT(11) DEFAULT 50 AFTER `ping_interval`;");
        message("Upgraded 'devices' table: added 'icon_size' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_size')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_size` INT(11) DEFAULT 14 AFTER `icon_size`;");
        message("Upgraded 'devices' table: added 'name_text_size' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'icon_url')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `icon_url` VARCHAR(255) NULL AFTER `name_text_size`;");
        message("Upgraded 'devices' table: added 'icon_url' column for custom icons.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'warning_latency_threshold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `warning_latency_threshold` INT(11) NULL AFTER `icon_url`;");
        message("Upgraded 'devices' table: added 'warning_latency_threshold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'warning_packetloss_threshold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `warning_packetloss_threshold` INT(11) NULL AFTER `warning_latency_threshold`;");
        message("Upgraded 'devices' table: added 'warning_packetloss_threshold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'critical_latency_threshold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `critical_latency_threshold` INT(11) NULL AFTER `warning_packetloss_threshold`;");
        message("Upgraded 'devices' table: added 'critical_latency_threshold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'critical_packetloss_threshold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `critical_packetloss_threshold` INT(11) NULL AFTER `critical_latency_threshold`;");
        message("Upgraded 'devices' table: added 'critical_packetloss_threshold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'last_avg_time')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `last_avg_time` DECIMAL(10, 2) NULL AFTER `critical_packetloss_threshold`;");
        message("Upgraded 'devices' table: added 'last_avg_time' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'last_ttl')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `last_ttl` INT(11) NULL AFTER `last_avg_time`;");
        message("Upgraded 'devices' table: added 'last_ttl' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'show_live_ping')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `show_live_ping` BOOLEAN DEFAULT FALSE AFTER `last_ttl`;");
        message("Upgraded 'devices' table: added 'show_live_ping' column.");
    }

    // Map background migrations
    if (!columnExists($pdo, $dbname, 'maps', 'background_color')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `background_color` VARCHAR(20) NULL AFTER `description`;");
        message("Upgraded 'maps' table: added 'background_color' column.");
    }
    if (!columnExists($pdo, $dbname, 'maps', 'background_image_url')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `background_image_url` VARCHAR(255) NULL AFTER `background_color`;");
        message("Upgraded 'maps' table: added 'background_image_url' column.");
    }

    // Step 5: Check if the admin user has any maps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `maps` WHERE user_id = ?");
    $stmt->execute([$admin_id]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO `maps` (user_id, name, type, is_default) VALUES (?, 'Default LAN Map', 'lan', TRUE)")->execute([$admin_id]);
        message("Created a default map for the admin user.");
    }

    // Step 6: Generate and store installation_id if not present
    $existing_installation_id = getInstallationId();
    if (empty($existing_installation_id)) {
        $new_installation_id = generateUuid();
        setInstallationId($new_installation_id);
        message("Generated and stored new installation ID: " . $new_installation_id);
    } else {
        message("Existing installation ID found: " . $existing_installation_id);
    }

    // Step 7: Indexing for Performance
    message("Applying database indexes for performance...");
    $indexes = [
        'ping_results' => ['idx_host_created_at' => '(`host`, `created_at` DESC)'],
        'devices' => [
            'idx_ip' => '(`ip`)',
            'idx_map_id' => '(`map_id`)',
            'idx_user_id' => '(`user_id`)'
        ],
        'device_status_logs' => ['idx_device_created' => '(`device_id`, `created_at` DESC)']
    ];

    foreach ($indexes as $table => $indexList) {
        foreach ($indexList as $indexName => $columns) {
            if (!indexExists($pdo, $dbname, $table, $indexName)) {
                $pdo->exec("CREATE INDEX `$indexName` ON `$table` $columns;");
                message("Created index '$indexName' on table '$table'.");
            } else {
                message("Index '$indexName' on table '$table' already exists.");
            }
        }
    }

    echo "<h2 style='color: #06b6d4; font-family: sans-serif;'>Database setup completed successfully!</h2>";
    echo "<p style='color: #94a3b8;'><span class='loader'></span>Redirecting to license setup in 3 seconds...</p>"; // Changed redirect
    echo '<meta http-equiv="refresh" content="3;url=license_setup.php">'; // Redirect to license setup
    
} catch (PDOException $e) {
    message("Database setup failed: " . $e->getMessage(), true);
    exit(1);
}
?>
    <a href="index.php" style="color: #22d3ee; text-decoration: none; font-size: 1.2rem;">&larr; Go to Dashboard</a>
</body>
</html>