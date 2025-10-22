<?php
// This is the new central bootstrap file.
// It handles basic setup like loading functions and checking database integrity.

// Load main application configuration first
require_once __DIR__ . '/../config.php';

// Then load general utility functions
require_once __DIR__ . '/functions.php';

// This script should not run on the setup page itself to avoid a redirect loop.
$current_page = basename($_SERVER['PHP_SELF']);

if ($current_page !== 'database_setup.php') {
    try {
        $pdo = getDbConnection();
        // A simple query to check if the main 'users' table exists.
        // If this fails, we assume the database has not been initialized.
        if (!tableExists($pdo, 'users')) {
            header('Location: database_setup.php');
            exit;
        }

        // After initial database setup, check for the app_settings table
        if (!tableExists($pdo, 'app_settings')) {
            header('Location: database_setup.php'); // Redirect to setup to create missing tables
            exit;
        }

        // If app_settings table exists, then check for the application license key
        if ($current_page !== 'license_setup.php') {
            $app_license_key = getAppLicenseKey();
            if (empty($app_license_key)) { // Use empty() for a more robust check
                header('Location: license_setup.php');
                exit;
            }
        }

    } catch (PDOException $e) {
        // A critical database error occurred (e.g., connection failed).
        // This is distinct from "table not found" which is handled by tableExists.
        die("A critical database error occurred: " . $e->getMessage());
    }
}

// Start session management after DB check.
// This ensures sessions are available on all pages that include this bootstrap.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}