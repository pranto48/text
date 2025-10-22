<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

// Ensure only admin can perform these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can perform maintenance operations.']);
    exit;
}

switch ($action) {
    case 'docker_update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $image_name = $input['image_name'] ?? 'arifmahmudpranto/ampnm';
            
            // 1. Pull the latest image
            // Note: This command relies on the container having access to the Docker CLI/socket, which is common in development but requires specific setup.
            // We use shell_exec to run the command.
            $pull_command = "docker pull " . escapeshellarg($image_name) . " 2>&1";
            $pull_output = shell_exec($pull_command);
            
            // Check if pull was successful (Docker output contains success messages)
            if (strpos($pull_output, 'Status: Downloaded newer image') === false && strpos($pull_output, 'Status: Image is up to date') === false) {
                // If pull fails, return the error output
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Docker pull failed. Output: ' . $pull_output]);
                exit;
            }

            // 2. Instruct user to restart the application server via the Dyad UI command.
            echo json_encode([
                'success' => true, 
                'message' => "Successfully pulled latest image: {$image_name}. Please use the <dyad-command type=\"restart\"></dyad-command> button above to restart the application server and apply the update."
            ]);
        }
        break;
}