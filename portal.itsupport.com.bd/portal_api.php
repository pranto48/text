<?php
require_once 'includes/functions.php';

// Ensure customer is logged in for all API actions
if (!isCustomerLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'get_profile':
        $customer_data = getCustomerData($customer_id);
        $profile_data = getProfileData($customer_id);
        echo json_encode(array_merge($customer_data, $profile_data));
        break;

    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $first_name = trim($input['first_name'] ?? '');
            $last_name = trim($input['last_name'] ?? '');
            $address = trim($input['address'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $avatar_url = trim($input['avatar_url'] ?? '');

            if (empty($first_name) || empty($last_name)) {
                http_response_code(400);
                echo json_encode(['error' => 'First Name and Last Name are required.']);
                exit;
            }

            if (updateCustomerProfile($customer_id, $first_name, $last_name, $address, $phone, $avatar_url)) {
                // Update session name if first/last name changed
                $_SESSION['customer_name'] = $first_name . ' ' . $last_name;
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update profile.']);
            }
        }
        break;
        
    case 'get_products': // NEW ACTION
        $stmt = $pdo->query("SELECT id, name, description, price, max_devices, license_duration_days FROM `products` ORDER BY price ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'products' => $products]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid API action.']);
        break;
}
?>