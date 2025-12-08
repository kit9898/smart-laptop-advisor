<?php
/**
 * Export Unrecognized Queries
 * Exports unrecognized queries to CSV for analysis
 * Part of Smart Laptop Advisor - Admin Module
 */

require_once '../../LaptopAdvisor/includes/db_connect.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="unrecognized_queries_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'Query',
    'Occurrences',
    'First Seen',
    'Last Seen',
    'Status'
]);

// Fetch unrecognized queries from database
$query = "SELECT 
    message_content as query_text,
    COUNT(*) as occurrences,
    MIN(timestamp) as first_seen,
    MAX(timestamp) as last_seen
FROM conversation_messages
WHERE message_type = 'user' 
  AND (intent_detected IS NULL OR intent_detected = '' OR intent_detected = 'fallback')
  AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY message_content
ORDER BY occurrences DESC, last_seen DESC
LIMIT 500";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['query_text'],
            $row['occurrences'],
            date('Y-m-d H:i:s', strtotime($row['first_seen'])),
            date('Y-m-d H:i:s', strtotime($row['last_seen'])),
            'Unrecognized'
        ]);
    }
} else {
    // If no data, write a message row
    fputcsv($output, [
        'No unrecognized queries found in the last 30 days',
        '',
        '',
        '',
        ''
    ]);
}

fclose($output);
$conn->close();
exit;
?>
