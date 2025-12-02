<?php
/**
 * Advanced Reports & Analytics Page  
 * Additional Tools Module
 * Generate comprehensive reports across all platform modules
 */

// Start session and include necessary files
session_start();
require_once 'includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$page_title = "Advanced Reports";

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
    <link rel="stylesheet" href="source/assets/vendors/apexcharts/apexcharts.css">
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
                            <h3>Comprehensive Reports & Analytics</h3>
                            <p class="text-subtitle text-muted">Generate detailed reports and analytics for all platform modules</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Reports</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                
                <!-- Report Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Report Filters & Export</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="reportType">Report Type</label>
                                            <select class="form-select" id="reportType">
                                                <option value="all">All Reports</option>
                                                <option value="sales">Sales & Revenue</option>
                                                <option value="products">Product Performance</option>
                                                <option value="customers">Customer Analytics</option>
                                                <option value="ai">AI Recommendations</option>
                                                <option value="chatbot">Chatbot Performance</option>
                                                <option value="inventory">Inventory Reports</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="dateFrom">From Date</label>
                                            <input type="date" class="form-control" id="dateFrom" value="2024-01-01">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="dateTo">To Date</label>
                                            <input type="date" class="form-control" id="dateTo" value="2024-12-31">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="btn-group w-100">
                                                <button type="button" class="btn btn-primary" onclick="generateReport()">
                                                    <i class="bi bi-search"></i> Generate
                                                </button>
                                                <button type="button" class="btn btn-success" onclick="exportReport()">
                                                    <i class="bi bi-download"></i> Export CSV
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon purple">
                                            <i class="iconly-boldShow"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Total Reports Generated</h6>
                                        <h6 class="font-extrabold mb-0">1,247</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon blue">
                                            <i class="iconly-boldProfile"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Data Points Analyzed</h6>
                                        <h6 class="font-extrabold mb-0">89,432</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon green">
                                            <i class="iconly-boldAdd-User"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Active Users</h6>
                                        <h6 class="font-extrabold mb-0">2,847</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon red">
                                            <i class="iconly-boldBookmark"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Report Exports</h6>
                                        <h6 class="font-extrabold mb-0">567</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and detailed views would go here -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Sales & Revenue Analytics</h4>
                            </div>
                            <div class="card-body">
                                <div id="chart-sales-revenue"></div>
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
    <script src="source/assets/vendors/apexcharts/apexcharts.js"></script>

    <script>
        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            alert(`Generating ${reportType} report from ${dateFrom} to ${dateTo}`);
        }

        function exportReport() {
            const csvContent = "data:text/csv;charset=utf-8,Report Name,Value,Date\nTotal Revenue,847392,2024-12-15";
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "comprehensive_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Sales Revenue Chart
        var salesRevenueOptions = {
            series: [{
                name: 'Revenue',
                data: [44000, 55000, 57000, 56000, 61000, 58000, 63000, 60000, 66000, 69000, 72000, 84000]
            }],
            chart: {
                type: 'line',
                height: 350
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            }
        };
        var salesRevenueChart = new ApexCharts(document.querySelector("#chart-sales-revenue"), salesRevenueOptions);
        salesRevenueChart.render();
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>
