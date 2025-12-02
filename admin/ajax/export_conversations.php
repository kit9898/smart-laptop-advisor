<?php
// ============================================
// Export Conversations Handler
// Module D: Smart Laptop Advisor Admin
// ============================================

require_once '../../LaptopAdvisor/includes/db_connect.php';
require_once '../../LaptopAdvisor/includes/config.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=conversation_logs_' . date('Y-m-d_H-i') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Session ID',
    'User Name',
    'User Email/IP',
    'Start Time',
    'End Time',
    'Duration',
    'Message Count',
    'User Messages',
    'Bot Messages',
    'Sentiment',
    'Outcome',
    'Source',
    'Full Transcript'
]);

// Build Query with Filters
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where_clauses[] = "(c.session_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Sentiment filter
if (isset($_GET['sentiment']) && $_GET['sentiment'] !== 'all') {
    $where_clauses[] = "c.sentiment = ?";
    $params[] = $_GET['sentiment'];
    $types .= "s";
}

// Time filter
if (isset($_GET['time']) && $_GET['time'] !== 'all') {
    switch ($_GET['time']) {
        case 'today':
            $where_clauses[] = "DATE(c.started_at) = CURDATE()";
            break;
        case 'week':
            $where_clauses[] = "c.started_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $where_clauses[] = "c.started_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
}

$where_sql = implode(" AND ", $where_clauses);

$query = "SELECT c.*, u.full_name, u.email 
          FROM conversations c 
          LEFT JOIN users u ON c.user_id = u.user_id 
          WHERE $where_sql 
          ORDER BY c.started_at DESC";

// Execute Query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Fetch and Output Data
while ($row = $result->fetch_assoc()) {
    // Format Duration
    $minutes = floor($row['duration_seconds'] / 60);
    $seconds = $row['duration_seconds'] % 60;
    $duration = sprintf("%dm %ds", $minutes, $seconds);
    
    // Fetch Transcript
    $transcript = "";
    $msg_query = "SELECT message_type, message_content, timestamp FROM conversation_messages WHERE conversation_id = ? ORDER BY timestamp ASC";
    $msg_stmt = $conn->prepare($msg_query);
    $msg_stmt->bind_param("i", $row['conversation_id']);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->get_result();
    
    while ($msg = $msg_result->fetch_assoc()) {
        $sender = ($msg['message_type'] === 'user') ? 'User' : 'Bot';
        $time = date('H:i:s', strtotime($msg['timestamp']));
        $transcript .= "[$time] $sender: " . $msg['message_content'] . "\n";
    }
    $msg_stmt->close();
    
    // Output Row
    fputcsv($output, [
        $row['session_id'],
        $row['full_name'] ?? 'Anonymous',
        $row['email'] ?? $row['user_ip'],
        $row['started_at'],
        $row['ended_at'],
        $duration,
        $row['message_count'],
        $row['user_message_count'],
        $row['bot_message_count'],
        ucfirst($row['sentiment']),
        ucwords(str_replace('_', ' ', $row['outcome'])),
        $row['source'],
        $transcript
    ]);
}

fclose($output);
$conn->close();
?>
