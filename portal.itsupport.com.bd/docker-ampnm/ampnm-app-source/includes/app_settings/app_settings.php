<?php
require_once __DIR__ . '/../utils/db_helpers.php'; // For tableExists

// --- Application Settings Functions ---
function getAppLicenseKey() {
    $pdo = getDbConnection();
    // Check if app_settings table exists before querying
    if (!tableExists($pdo, 'app_settings')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = 'app_license_key'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : null;
}

function setAppLicenseKey($license_key) {
    $pdo = getDbConnection();
    // Use UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) to either insert or update the key
    $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES ('app_license_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$license_key, $license_key]);
}

function getLastLicenseCheck() {
    $pdo = getDbConnection();
    if (!tableExists($pdo, 'app_settings')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = 'last_license_check'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : null;
}

function setLastLicenseCheck($timestamp) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES ('last_license_check', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$timestamp, $timestamp]);
}

function getInstallationId() {
    $pdo = getDbConnection();
    if (!tableExists($pdo, 'app_settings')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = 'installation_id'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : null;
}

function setInstallationId($installation_id) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES ('installation_id', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$installation_id, $installation_id]);
}