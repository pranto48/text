<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$license_key = $_GET['license_key'] ?? null;

if (!$license_key) {
    http_response_code(400);
    echo "License key is required.";
    exit;
}

// Define the base directory for the Docker project files
$docker_project_base_dir = __DIR__ . '/docker-ampnm/';
$app_source_dir = $docker_project_base_dir . 'ampnm-app-source/';

// Ensure the app source directory exists
if (!is_dir($app_source_dir)) {
    http_response_code(500);
    error_log("Error in download_ampnm_docker_project.php: Application source directory not found at " . $app_source_dir);
    echo "Error: Application source directory not found.";
    exit;
}

// Get content for Dockerfile and docker-compose.yml
$dockerfile_content = getDockerfileContent();
$docker_compose_content = getDockerComposeContent($license_key);

// Read docker-entrypoint.sh content
$entrypoint_script_path = $docker_project_base_dir . 'docker-entrypoint.sh';
$entrypoint_content = '';
if (!file_exists($entrypoint_script_path)) {
    http_response_code(500);
    error_log("Error in download_ampnm_docker_project.php: docker-entrypoint.sh not found at " . $entrypoint_script_path);
    echo "Error: docker-entrypoint.sh not found.";
    exit;
}
try {
    $entrypoint_content = file_get_contents($entrypoint_script_path);
    if ($entrypoint_content === false) {
        throw new Exception("Failed to read docker-entrypoint.sh content.");
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in download_ampnm_docker_project.php: " . $e->getMessage());
    echo "Error: Failed to read docker-entrypoint.sh content. Check file permissions.";
    exit;
}


// Create a temporary zip file
$zip_file_name = 'ampnm-docker-project-' . date('YmdHis') . '.zip';
$zip_file_path = sys_get_temp_dir() . '/' . $zip_file_name;

$zip = new ZipArchive();
if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    error_log("Error in download_ampnm_docker_project.php: Could not create zip file at " . $zip_file_path . ". Check permissions for " . sys_get_temp_dir());
    echo "Error: Could not create zip file. Check server's temporary directory permissions.";
    exit;
}

try {
    // Add the root directory for the project inside the zip
    $zip_root_folder = 'docker-ampnm/';
    $zip->addEmptyDir($zip_root_folder);

    // Add dynamically generated files to the zip
    $zip->addFromString($zip_root_folder . 'Dockerfile', $dockerfile_content);
    $zip->addFromString($zip_root_folder . 'docker-compose.yml', $docker_compose_content);
    $zip->addFromString($zip_root_folder . 'docker-entrypoint.sh', $entrypoint_content);

    // Add the entire ampnm-app-source directory recursively
    addFolderToZip($zip, $app_source_dir, $zip_root_folder . 'ampnm-app-source');

    // Add the .gitkeep file if it exists
    $gitkeep_path = $docker_project_base_dir . '.gitkeep';
    if (file_exists($gitkeep_path)) {
        $zip->addFile($gitkeep_path, $zip_root_folder . '.gitkeep');
    }

    $zip->close();
} catch (Exception $e) {
    $zip->close(); // Ensure zip is closed even on error
    unlink($zip_file_path); // Clean up partial zip file
    http_response_code(500);
    error_log("Error in download_ampnm_docker_project.php during zip creation: " . $e->getMessage());
    echo "Error: Failed to add files to zip. This might be due to file permissions or corrupted files in the source directory.";
    exit;
}


// Set headers for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
header('Content-Length: ' . filesize($zip_file_path));
header('Pragma: no-cache');
header('Expires: 0');

// Output the zip file and delete the temporary file
readfile($zip_file_path);
unlink($zip_file_path);

exit;