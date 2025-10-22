<?php
require_once '../includes/functions.php';
require_once '../config.php'; // Ensure LICENSE_DB_ constants are available

// Ensure only authenticated admins can access this API
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access. Admin login required.']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Ensure the license database is reachable before proceeding
if (!checkLicenseDbConnection()) {
    http_response_code(500);
    echo json_encode(['error' => 'License database is not reachable. Cannot perform operation.']);
    exit;
}

switch ($action) {
    case 'backup_db':
        $backup_dir = __DIR__ . '/../uploads/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $filename = 'license_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;

        // Use credentials from config.php
        $db_host = escapeshellarg(LICENSE_DB_SERVER);
        $db_name = escapeshellarg(LICENSE_DB_NAME);
        $db_user = escapeshellarg(LICENSE_DB_USERNAME);
        $db_pass = escapeshellarg(LICENSE_DB_PASSWORD);

        // Construct the mysqldump command
        // For security, pass password via --password= rather than -pPASSWORD
        $command = "mysqldump --host=$db_host --user=$db_user --password=$db_pass $db_name > $filepath 2>&1";
        
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Database backup created successfully.',
                'filename' => $filename,
                'download_url' => 'uploads/backups/' . $filename // Relative path for download
            ]);
        } else {
            error_log("Database backup failed. Command: $command. Output: " . implode("\n", $output));
            http_response_code(500);
            echo json_encode(['error' => 'Database backup failed: ' . implode("\n", $output)]);
        }
        break;

    case 'restore_db':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['backup_file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No backup file uploaded or invalid request method.']);
            exit;
        }

        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(500);
            echo json_encode(['error' => 'File upload error: ' . $file['error']]);
            exit;
        }

        $temp_filepath = $file['tmp_name'];
        $original_filename = basename($file['name']);

        // Validate file extension
        if (pathinfo($original_filename, PATHINFO_EXTENSION) !== 'sql') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Only .sql files are allowed.']);
            unlink($temp_filepath); // Delete temp file
            exit;
        }

        // Use credentials from config.php
        $db_host = escapeshellarg(LICENSE_DB_SERVER);
        $db_name = escapeshellarg(LICENSE_DB_NAME);
        $db_user = escapeshellarg(LICENSE_DB_USERNAME);
        $db_pass = escapeshellarg(LICENSE_DB_PASSWORD);

        // Construct the mysql command
        // For security, pass password via --password= rather than -pPASSWORD
        $command = "mysql --host=$db_host --user=$db_user --password=$db_pass $db_name < $temp_filepath 2>&1";
        
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        unlink($temp_filepath); // Delete temp file after use

        if ($return_var === 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Database restored successfully from ' . htmlspecialchars($original_filename) . '.'
            ]);
        } else {
            error_log("Database restore failed. Command: $command. Output: " . implode("\n", $output));
            http_response_code(500);
            echo json_encode(['error' => 'Database restore failed: ' . implode("\n", $output)]);
        }
        break;

    case 'get_all_licenses': // NEW ACTION
        try {
            $pdo = getLicenseDbConnection(); // Ensure PDO is initialized for the license DB
            $search_term = $_GET['search'] ?? '';

            $sql = "
                SELECT l.*, c.email as customer_email, p.name as product_name
                FROM `licenses` l
                LEFT JOIN `customers` c ON l.customer_id = c.id
                LEFT JOIN `products` p ON l.product_id = p.id
            ";
            $params = [];

            if (!empty($search_term)) {
                $sql .= " WHERE l.license_key LIKE ? OR c.email LIKE ?";
                $params[] = "%{$search_term}%";
                $params[] = "%{$search_term}%";
            }
            $sql .= " ORDER BY l.created_at DESC";

            $stmt_licenses = $pdo->prepare($sql);
            $stmt_licenses->execute($params);
            $licenses = $stmt_licenses->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'licenses' => $licenses]);
        } catch (PDOException $e) {
            error_log("Error fetching licenses from admin_api.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            error_log("Unexpected error in admin_api.php (get_all_licenses): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid API action.']);
        break;
}