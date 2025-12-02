<?php
session_start();
require_once 'includes/db_connect.php';

// Build Query
$where_clauses = [];
$params = [];
$types = "";

// Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_method_filter = $_GET['payment_method'] ?? '';
$flagged_filter = $_GET['flagged'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "o.order_date BETWEEN ? AND ?";
    $params[] = $start_date . " 00:00:00";
    $params[] = $end_date . " 23:59:59";
    $types .= "ss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "o.order_status = ?";
    $params[] = ucfirst($status_filter);
    $types .= "s";
}

if (!empty($payment_method_filter)) {
    $where_clauses[] = "o.payment_method = ?";
    $params[] = $payment_method_filter;
    $types .= "s";
}

if ($flagged_filter == '1') {
    $where_clauses[] = "o.is_flagged = 1";
}

$query = "SELECT o.*, u.full_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.user_id";
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
$total_revenue = 0;
$successful_txns = 0;
$pending_txns = 0;
$failed_txns = 0;

while ($row = $result->fetch_assoc()) {
    // Calculate stats
    $total_revenue += $row['total_amount'];
    $st = strtolower($row['order_status']);
    if ($st == 'completed' || $st == 'delivered' || $st == 'shipped') $successful_txns++;
    elseif ($st == 'pending' || $st == 'processing') $pending_txns++;
    elseif ($st == 'cancelled' || $st == 'failed' || $st == 'refunded') $failed_txns++;

    // Prepare display data
    $row['transaction_id'] = 'TXN-' . date('Y', strtotime($row['order_date'])) . '-' . str_pad($row['order_id'], 5, '0', STR_PAD_LEFT);
    $row['payment_method'] = $row['payment_method'] ?: 'Credit Card';
    
    // Badge Class for Print
    switch (strtolower($row['order_status'])) {
        case 'completed': 
        case 'delivered': 
        case 'shipped':
            $row['status_class'] = 'success'; 
            break;
        case 'pending': 
        case 'processing':
            $row['status_class'] = 'warning'; 
            break;
        case 'refunded': 
        case 'cancelled':
            $row['status_class'] = 'info'; 
            break;
        default: 
            $row['status_class'] = 'danger'; 
            break;
    }
    
    $transactions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Report - <?php echo date('Y-m-d'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            margin: 20px;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
        }
        
        .report-header h1 {
            margin: 0;
            font-size: 24pt;
            color: #333;
        }
        
        .report-date {
            color: #666;
            font-size: 11pt;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
        }
        
        .stat-label {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 16pt;
            font-weight: bold;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-size: 10pt;
        }
        
        td {
            padding: 8px;
            border: 1px solid #dee2e6;
            font-size: 10pt;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .badge-success { background-color: #28a745; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-info { background-color: #17a2b8; color: white; }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                margin: 0;
            }
            
            @page {
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex justify-content-between no-print mb-3">
        <div>
            <button class="btn btn-primary" onclick="window.print()">Print Report</button>
            <button class="btn btn-secondary ms-2" onclick="window.close()">Close</button>
        </div>
        <div>
            <!-- Optional: Add filters summary or other actions -->
        </div>
    </div>
    
    <div class="report-header">
        <h1>Smart Laptop Advisor</h1>
        <h2>Transaction Report</h2>
        <div class="report-date">Generated: <?php echo date('F d, Y h:i A'); ?></div>
        <?php if($start_date && $end_date): ?>
            <div class="report-date">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></div>
        <?php endif; ?>
    </div>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Successful</div>
            <div class="stat-value" style="color: #28a745;"><?php echo $successful_txns; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color: #ffc107;"><?php echo $pending_txns; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Failed/Refunded</div>
            <div class="stat-value" style="color: #dc3545;"><?php echo $failed_txns; ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $txn): ?>
            <tr>
                <td>
                    <?php echo $txn['transaction_id']; ?>
                    <?php if ($txn['is_flagged']): ?>
                        <span class="badge badge-danger" style="font-size: 8pt;">Flagged</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('Y-m-d H:i', strtotime($txn['order_date'])); ?></td>
                <td>
                    <?php echo htmlspecialchars($txn['full_name'] ?? 'Guest'); ?><br>
                    <small style="color: #888;"><?php echo htmlspecialchars($txn['email'] ?? ''); ?></small>
                </td>
                <td>$<?php echo number_format($txn['total_amount'], 2); ?></td>
                <td><?php echo $txn['payment_method']; ?></td>
                <td>
                    <span class="badge badge-<?php echo $txn['status_class']; ?>">
                        <?php echo ucfirst($txn['order_status']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="print-footer">
        <p>Smart Laptop Advisor - Financial Transaction Report</p>
        <p>This report is confidential and intended for internal use only.</p>
    </div>
    
    <script>
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
