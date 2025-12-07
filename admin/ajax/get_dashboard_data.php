<?php
// ajax/get_dashboard_data.php
require_once '../includes/db_connect.php';

$period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
$format = isset($_GET['format']) ? $_GET['format'] : 'json';

if ($period <= 0) $period = 30;

// Query
$sql = "SELECT DATE(order_date) as date, SUM(total_amount) as revenue, COUNT(*) as order_count 
        FROM orders 
        WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) 
          AND order_status != 'Cancelled'
        GROUP BY DATE(order_date) 
        ORDER BY date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $period);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_revenue_report_' . $period . 'days.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Revenue ($)', 'Orders']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['date'], 
            number_format($row['revenue'], 2, '.', ''), 
            $row['order_count']
        ]);
    }
    fclose($output);
    exit;
} else {
    // JSON Response
    header('Content-Type: application/json');
    
    $json_data = [
        'categories' => [],
        'revenue' => [],
        'orders' => []
    ];
    
    foreach ($data as $row) {
        $json_data['categories'][] = date('M d', strtotime($row['date']));
        $json_data['revenue'][] = (float)$row['revenue'];
        $json_data['orders'][] = (int)$row['order_count'];
    }
    
    echo json_encode($json_data);
}
?>
