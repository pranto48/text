<?php
// This script is intended to be run from the command line to check DB connectivity.
// It will exit with status 0 on success, 1 on failure.

// Suppress warnings for failed connection attempts during startup
error_reporting(0);

$host = getenv('DB_HOST'); // Use the DB_HOST environment variable
$user = 'root'; // The entrypoint script needs root to ensure the DB is up, before setup runs.
$pass = getenv('MYSQL_ROOT_PASSWORD');

try {
    // Attempt to connect to the MySQL server. We don't need to select a DB yet.
    new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
    exit(0); // Success
} catch (PDOException $e) {
    exit(1); // Failure
}