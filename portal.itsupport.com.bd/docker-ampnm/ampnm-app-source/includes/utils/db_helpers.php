<?php
// Helper function to check if a table exists in the current database connection
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
    } catch (PDOException $e) {
        // We only care about "table not found" errors
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            return false;
        }
        // For other errors, re-throw or log
        throw $e;
    }
    return $result !== false;
}

/**
 * Generates a UUID v4.
 * @return string A UUID string.
 */
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord(ord($data[8]) & 0x3f | 0x80)); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}