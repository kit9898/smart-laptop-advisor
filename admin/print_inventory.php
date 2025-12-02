<?php
/* 
ALTERNATIVE: Create a separate print-optimized page
Create a new file: print_inventory.php
*/
?>

<!-- print_inventory.php - Separate print page -->
<?php
session_start();
require_once 'includes/db_connect.php';

// Fetch all inventory data
$query = "SELECT * FROM products ORDER BY product_id DESC";
$result = $conn->query($query);
$inventory = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['sku'] = 'SKU-' . str_pad($row['product_id'], 5, '0', STR_PAD_LEFT);
        
        if ($row['stock_quantity'] == 0) {
            $row['status_badge'] = 'Out of Stock';
            $row['status_class'] = 'danger';
        } elseif ($row['stock_quantity'] < $row['min_stock_level']) {
            $row['status_badge'] = 'Low Stock';
            $row['status_class'] = 'warning';
        } else {
            $row['status_badge'] = 'In Stock';
            $row['status_class'] = 'success';
        }
        
        $inventory[] = $row;
    }
}

$total_products = count($inventory);
$in_stock = count(array_filter($inventory, function($i) { return $i['stock_quantity'] >= $i['min_stock_level']; }));
$low_stock = count(array_filter($inventory, function($i) { return $i['stock_quantity'] > 0 && $i['stock_quantity'] < $i['min_stock_level']; }));
$out_of_stock = count(array_filter($inventory, function($i) { return $i['stock_quantity'] == 0; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Report - <?php echo date('Y-m-d'); ?></title>
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
            font-size: 20pt;
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
    <button class="btn btn-primary no-print" onclick="window.print()">Print Report</button>
    <button class="btn btn-secondary no-print ms-2" onclick="window.close()">Close</button>
    <hr class="no-print">
    
    <div class="report-header">
        <h1>Smart Laptop Advisor</h1>
        <h2>Inventory Stock Report</h2>
        <div class="report-date">Generated: <?php echo date('F d, Y h:i A'); ?></div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Total Products</div>
            <div class="stat-value"><?php echo $total_products; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">In Stock</div>
            <div class="stat-value" style="color: #28a745;"><?php echo $in_stock; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Low Stock</div>
            <div class="stat-value" style="color: #ffc107;"><?php echo $low_stock; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Out of Stock</div>
            <div class="stat-value" style="color: #dc3545;"><?php echo $out_of_stock; ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Brand</th>
                <th>Category</th>
                <th>Current Stock</th>
                <th>Min Stock</th>
                <th>Price</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventory as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td><?php echo htmlspecialchars($item['brand']); ?></td>
                <td><?php echo htmlspecialchars($item['product_category']); ?></td>
                <td><strong><?php echo $item['stock_quantity']; ?></strong></td>
                <td><?php echo $item['min_stock_level']; ?></td>
                <td>$<?php echo number_format($item['price'], 2); ?></td>
                <td>
                    <span class="badge badge-<?php echo $item['status_class']; ?>">
                        <?php echo $item['status_badge']; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="print-footer">
        <p>Smart Laptop Advisor - Inventory Management System</p>
        <p>This report is confidential and intended for internal use only.</p>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>

<?php
// Then update the button in inventory.php to open this page:
?>
<button class="btn btn-outline-secondary" onclick="window.open('print_inventory.php', '_blank')">
    <i class="bi bi-printer me-2"></i>Print Stock Report
</button>