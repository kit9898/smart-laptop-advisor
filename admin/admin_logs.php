<?php
/**
 * System Logs Page
 * Additional Tools Module
 * Monitor system activities, errors, and security events
 */

// Start session and include necessary files
session_start();
require_once 'includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$page_title = "System Logs";

// Fetch recent logs from admin_activity_log
$logs_query = "SELECT al.*, CONCAT(au.first_name, ' ', au.last_name) as admin_name
               FROM admin_activity_log al
               LEFT JOIN admin_users au ON al.admin_id = au.admin_id
               ORDER BY al.created_at DESC
               LIMIT 50";
$logs_result = mysqli_query($conn, $logs_query);
$logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) {
    $logs[] = $row;
}

// ==================== VIEW LAYER ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Smart Laptop Advisor Admin</title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="source/assets/css/bootstrap.css">
    <link rel="stylesheet" href="source/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="source/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="source/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="source/assets/css/app.css">
    <link rel="shortcut icon" href="source/assets/images/favicon.svg" type="image/x-icon">
</head>

<body>
    <div id="app">
        <?php include 'includes/admin_header.php'; ?>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>System Activity Logs</h3>
                            <p class="text-subtitle text-muted">Monitor system activities, errors, and security events</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">System Logs</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                
                <!-- Log Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Log Filters & Search</h4>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="actionFilter">Action Type</label>
                                                <select class="form-select" id="actionFilter" name="action">
                                                    <option value="">All Actions</option>
                                                    <option value="login">Login</option>
                                                    <option value="logout">Logout</option>
                                                    <option value="create">Create</option>
                                                    <option value="update">Update</option>
                                                    <option value="delete">Delete</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="logSearch">Search Logs</label>
                                                <input type="text" class="form-control" id="logSearch" name="search" 
                                                       placeholder="Search by message, user, or IP...">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-search"></i> Filter
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="button" class="btn btn-success w-100" onclick="exportLogs()">
                                                    <i class="bi bi-download"></i> Export Logs
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Statistics -->
                <div class="row">
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon blue">
                                            <i class="iconly-boldShow"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Total Logs</h6>
                                        <h6 class="font-extrabold mb-0"><?= count($logs) ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Log Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>System Log History</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Action</th>
                                                <th>User</th>
                                                <th>Description</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log): ?>
                                                <tr>
                                                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                                    <td><span class="badge bg-primary"><?= htmlspecialchars($log['action']) ?></span></td>
                                                    <td><?= htmlspecialchars($log['admin_name'] ?? 'System') ?></td>
                                                    <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (count($logs) === 0): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No logs found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="source/assets/js/bootstrap.js"></script>
    <script src="source/assets/js/app.js"></script>

    <script>
        function exportLogs() {
            const csvContent = "data:text/csv;charset=utf-8,Timestamp,Action,User,Description,IP\\n<?php foreach ($logs as $log): ?><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>,<?= $log['action'] ?>,<?= $log['admin_name'] ?? 'System' ?>,<?= addslashes($log['description'] ?? '') ?>,<?= $log['ip_address'] ?? 'N/A' ?>\\n<?php endforeach; ?>";
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "system_logs.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>
