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
    echo "Error: Application source directory not found.";
    exit;
}

// Get content for Dockerfile and docker-compose.yml
$dockerfile_content = getDockerfileContent();
$docker_compose_content = getDockerComposeContent($license_key);

// Read docker-entrypoint.sh content
$entrypoint_script_path = $docker_project_base_dir . 'docker-entrypoint.sh';
if (!file_exists($entrypoint_script_path)) {
    http_response_code(500);
    echo "Error: docker-entrypoint.sh not found.";
    exit;
}
$entrypoint_content = file_get_contents($entrypoint_script_path);

// Create a temporary zip file
$zip_file_name = 'ampnm-docker-project-' . date('YmdHis') . '.zip';
$zip_file_path = sys_get_temp_dir() . '/' . $zip_file_name;

$zip = new ZipArchive();
if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    echo "Error: Could not create zip file.";
    exit;
}

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