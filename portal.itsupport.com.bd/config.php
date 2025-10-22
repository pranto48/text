<?php
// External License Service Database Configuration
// These values will be set during the setup process via license_setup.php

define('LICENSE_DB_SERVER', 'localhost');
define('LICENSE_DB_USERNAME', 'bdoldco1_itportal');
define('LICENSE_DB_PASSWORD', 'Aa329093+-*');
define('LICENSE_DB_NAME', 'bdoldco1_itportal');

// Function to create database connection for the license service
function getLicenseDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) {
                $pdo = null;
            } else {
                throw $e;
            }
        }
    }

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . LICENSE_DB_SERVER . ";dbname=" . LICENSE_DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, LICENSE_DB_USERNAME, LICENSE_DB_PASSWORD, $options);
        } catch(PDOException $e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the license database. " . $e->getMessage());
        }
    }
    
    return $pdo;
}