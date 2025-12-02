<?php
session_start();
require_once 'includes/db_connect.php';

// --- Logic: Fetch Statistics ---

// 1. Total Revenue
$sql_revenue = "SELECT SUM(total_amount) as total_revenue FROM orders";
$result_revenue = $conn->query($sql_revenue);
$row_revenue = $result_revenue->fetch_assoc();
$total_revenue = $row_revenue['total_revenue'] ?? 0;

// 2. Total Orders
$sql_orders = "SELECT COUNT(*) as total_orders FROM orders";
$result_orders = $conn->query($sql_orders);
$row_orders = $result_orders->fetch_assoc();
$total_orders = $row_orders['total_orders'] ?? 0;

// 3. Total Users
$sql_users = "SELECT COUNT(*) as total_users FROM users";
$result_users = $conn->query($sql_users);
$row_users = $result_users->fetch_assoc();
$total_users = $row_users['total_users'] ?? 0;

// 4. Chatbot Interactions
$sql_chat = "SELECT COUNT(*) as total_interactions FROM chat_history";
$result_chat = $conn->query($sql_chat);
$row_chat = $result_chat->fetch_assoc();
$total_interactions = $row_chat['total_interactions'] ?? 0;

// --- Logic: Fetch Recent Orders ---
$sql_recent = "SELECT o.order_id, u.full_name, o.total_amount, o.order_status, o.order_date 
               FROM orders o 
               JOIN users u ON o.user_id = u.user_id 
               ORDER BY o.order_date DESC 
               LIMIT 5";
$result_recent = $conn->query($sql_recent);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Laptop Advisor Admin</title>
    
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Analytics Dashboard</h3>
                        <p class="text-subtitle text-muted">Smart Laptop Advisor Platform Overview</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="input-group" style="max-width: 200px;">
                            <input type="date" class="form-control form-control-sm" id="dateRange">
                            <button class="btn btn-outline-secondary btn-sm" type="button">
                                <i class="bi bi-calendar"></i>
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-2"></i>Admin User
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <!-- Key Metrics Cards -->
                <section class="row">
                    <div class="col-12">
                        <div class="row">
                            <!-- Total Revenue -->
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-3 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="stats-icon purple">
                                                    <i class="iconly-boldBuy"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <h6 class="text-muted font-semibold">Total Revenue</h6>
                                                <h6 class="font-extrabold mb-0">$<?= number_format($total_revenue, 2) ?></h6>
                                                <small class="text-success"><i class="bi bi-arrow-up"></i> +12.5%</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- New Orders -->
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-3 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="stats-icon blue">
                                                    <i class="iconly-boldActivity"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <h6 class="text-muted font-semibold">Total Orders</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($total_orders) ?></h6>
                                                <small class="text-success"><i class="bi bi-arrow-up"></i> +8.2%</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Registered Users -->
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-3 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="stats-icon green">
                                                    <i class="iconly-boldProfile"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <h6 class="text-muted font-semibold">Registered Users</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($total_users) ?></h6>
                                                <small class="text-success"><i class="bi bi-arrow-up"></i> +15.3%</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chatbot Interactions -->
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-3 py-4-5">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="stats-icon red">
                                                    <i class="iconly-boldChat"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <h6 class="text-muted font-semibold">Chat Interactions</h6>
                                                <h6 class="font-extrabold mb-0"><?= number_format($total_interactions) ?></h6>
                                                <small class="text-success"><i class="bi bi-arrow-up"></i> +22.1%</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Charts Section -->
                <section class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4>Sales & Revenue Trends</h4>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary active" data-period="30">30 Days</button>
                                    <button class="btn btn-sm btn-outline-primary" data-period="90">90 Days</button>
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download"></i> Export CSV
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="chart-sales-revenue"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Best-Selling Products</h4>
                            </div>
                            <div class="card-body">
                                <div id="chart-best-products"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- AI Performance & Recent Activities -->
                <section class="row">
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>AI Recommendation Performance</h4>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h3 class="text-primary">87.5%</h3>
                                            <p class="text-muted mb-0">Accuracy Rate</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h3 class="text-success">23.2%</h3>
                                            <p class="text-muted mb-0">Conversion Rate</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Student Persona</small>
                                        <small>342 recommendations</small>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-primary" style="width: 45%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Gaming Persona</small>
                                        <small>287 recommendations</small>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: 38%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Business Persona</small>
                                        <small>156 recommendations</small>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-info" style="width: 21%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Recent System Activities</h4>
                            </div>
                            <div class="card-body">
                                <div class="activity-item d-flex align-items-center mb-3">
                                    <div class="activity-icon bg-success text-white rounded-circle me-3">
                                        <i class="bi bi-plus"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0">New laptop added: Dell XPS 13</p>
                                        <small class="text-muted">2 minutes ago</small>
                                    </div>
                                </div>
                                <div class="activity-item d-flex align-items-center mb-3">
                                    <div class="activity-icon bg-primary text-white rounded-circle me-3">
                                        <i class="bi bi-cart"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0">Order #ORD-2024-001 processed</p>
                                        <small class="text-muted">15 minutes ago</small>
                                    </div>
                                </div>
                                <div class="activity-item d-flex align-items-center mb-3">
                                    <div class="activity-icon bg-warning text-white rounded-circle me-3">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0">Low stock alert: MacBook Air M2</p>
                                        <small class="text-muted">1 hour ago</small>
                                    </div>
                                </div>
                                <div class="activity-item d-flex align-items-center mb-3">
                                    <div class="activity-icon bg-info text-white rounded-circle me-3">
                                        <i class="bi bi-robot"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0">AI model retrained successfully</p>
                                        <small class="text-muted">3 hours ago</small>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button class="btn btn-outline-primary btn-sm">View All Activities</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Quick Actions</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="d-grid">
                                            <button class="btn btn-primary" onclick="location.href='admin-products.html'">
                                                <i class="bi bi-plus-circle me-2"></i>Add New Product
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="d-grid">
                                            <button class="btn btn-success" onclick="location.href='admin-orders.html'">
                                                <i class="bi bi-eye me-2"></i>View Orders
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="d-grid">
                                            <button class="btn btn-info" onclick="location.href='admin-ai-weightage.html'">
                                                <i class="bi bi-sliders me-2"></i>Configure AI
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="d-grid">
                                            <button class="btn btn-warning" onclick="location.href='admin-conversation-logs.html'">
                                                <i class="bi bi-chat-dots me-2"></i>Check Chatbot
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Recent Orders Table (Dynamic) -->
                <section class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Recent Orders (Real-Time)</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-lg">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result_recent->num_rows > 0): ?>
                                                <?php while($row = $result_recent->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>#<?= htmlspecialchars($row['order_id']) ?></td>
                                                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                                                        <td>$<?= number_format($row['total_amount'], 2) ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = 'bg-secondary';
                                                            if ($row['order_status'] == 'Completed') $status_class = 'bg-success';
                                                            elseif ($row['order_status'] == 'Pending') $status_class = 'bg-warning';
                                                            elseif ($row['order_status'] == 'Cancelled') $status_class = 'bg-danger';
                                                            elseif ($row['order_status'] == 'Processing') $status_class = 'bg-info';
                                                            ?>
                                                            <span class="badge <?= $status_class ?>"><?= htmlspecialchars($row['order_status']) ?></span>
                                                        </td>
                                                        <td><?= date('M d, Y', strtotime($row['order_date'])) ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No recent orders found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>

    <script>
        // Sales & Revenue Chart
        var salesOptions = {
            series: [{
                name: 'Revenue',
                data: [31000, 40000, 28000, 51000, 42000, 82000, 56000, 68000, 91000, 125000, 98000, 87000]
            }, {
                name: 'Orders',
                data: [150, 180, 125, 220, 195, 310, 245, 285, 365, 425, 380, 340]
            }],
            chart: {
                height: 350,
                type: 'area',
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'
            },
            title: {
                text: 'Revenue & Orders Over Time',
                align: 'left'
            },
            grid: {
                row: {
                    colors: ['#f3f3f3', 'transparent'],
                    opacity: 0.5
                },
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            },
            colors: ['#435ebe', '#55c6e8']
        };

        var salesChart = new ApexCharts(document.querySelector("#chart-sales-revenue"), salesOptions);
        salesChart.render();

        // Best Products Chart
        var productsOptions = {
            series: [44, 55, 13, 43, 22],
            chart: {
                width: 380,
                type: 'donut',
            },
            labels: ['MacBook Pro M3', 'Dell XPS 13', 'ThinkPad X1', 'ASUS ROG', 'HP Spectre'],
            colors: ['#435ebe', '#55c6e8', '#1cc88a', '#f6c23e', '#e74a3b'],
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        };

        var productsChart = new ApexCharts(document.querySelector("#chart-best-products"), productsOptions);
        productsChart.render();

        // Period buttons functionality
        document.querySelectorAll('[data-period]').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('[data-period]').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                // Here you would typically reload the chart data for the selected period
            });
        });
    </script>
</body>
</html>
