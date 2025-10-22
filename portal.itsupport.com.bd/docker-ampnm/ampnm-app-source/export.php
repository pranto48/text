<?php
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDbConnection();

// Get filter parameters
$host = $_GET['host'] ?? '';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="ping_history_' . date('Y-m-d_H-i-s') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Host', 'Packet Loss (%)', 'Avg Time (ms)', 'Min Time (ms)', 'Max Time (ms)', 'Success', 'Timestamp']);

// Get data
$sql = "SELECT * FROM ping_results";
$params = [];
if ($host) {
    $sql .= " WHERE host = ?";
    $params[] = $host;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Loop through the results and output each row
foreach ($results as $row) {
    fputcsv($output, [
        $row['host'],
        $row['packet_loss'],
        $row['avg_time'],
        $row['min_time'],
        $row['max_time'],
        $row['success'] ? 'Yes' : 'No',
        $row['created_at']
    ]);
}

fclose($output);
exit;
?>