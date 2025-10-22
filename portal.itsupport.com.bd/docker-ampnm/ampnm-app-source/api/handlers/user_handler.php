<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

// Ensure only admin can perform these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage users.']);
    exit;
}

switch ($action) {
    case 'get_users':
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
        break;

    case 'create_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? 'user'; // Default to 'user' role

            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and password are required.']);
                exit;
            }

            // Validate role input
            if (!in_array($role, ['admin', 'user'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role specified.']);
                exit;
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists.']);
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        }
        break;

    case 'update_user_role': // NEW ACTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $new_role = $input['role'] ?? '';

            if (!$id || empty($new_role)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID and new role are required.']);
                exit;
            }

            // Validate role input
            if (!in_array($new_role, ['admin', 'user'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role specified.']);
                exit;
            }

            // Prevent admin from changing their own role or deleting themselves
            if ($id == $_SESSION['user_id'] && $new_role !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot change your own role from admin.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $id]);
            echo json_encode(['success' => true, 'message' => 'User role updated successfully.']);
        }
        break;

    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                exit;
            }

            // Prevent admin from deleting themselves
            if ($id == $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot delete your own admin account.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        }
        break;
}