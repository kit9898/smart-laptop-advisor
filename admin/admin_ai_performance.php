<?php
// admin_ai_performance.php - AI Performance Analytics
// Module C: AI Recommendation Engine

// Include database connection
require_once 'includes/db_connect.php';

// ===================== LOGIC SECTION =====================

// Fetch overall KPIs (User Satisfaction)
$kpi_query = "SELECT 
    COUNT(*) as total_ratings,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as total_likes,
    SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) as total_dislikes
    FROM recommendation_ratings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$kpi_result = mysqli_query($conn, $kpi_query);
$kpis = mysqli_fetch_assoc($kpi_result);

$total_ratings = $kpis['total_ratings'] > 0 ? $kpis['total_ratings'] : 1; // Avoid division by zero
$satisfaction_score = ($kpis['total_likes'] / $total_ratings) * 100;
$dislike_rate = ($kpis['total_dislikes'] / $total_ratings) * 100;

// Fetch performance by persona (based on User's Primary Use Case)
$persona_perf_query = "SELECT 
    p.name as persona_name,
    p.color_theme,
    COUNT(r.rating_id) as total_ratings,
    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as total_likes,
    (SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as satisfaction_score
    FROM personas p
    LEFT JOIN users u ON u.primary_use_case = p.name
    LEFT JOIN recommendation_ratings r ON u.user_id = r.user_id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR r.created_at IS NULL
    GROUP BY p.persona_id
    ORDER BY total_ratings DESC";
$persona_perf_result = mysqli_query($conn, $persona_perf_query);

// Fetch performance trends (last 7 days)
$trends_query = "SELECT 
    DATE(created_at) as log_date,
    COUNT(*) as total_ratings,
    (SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as daily_satisfaction
    FROM recommendation_ratings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY log_date ASC";
$trends_result = mysqli_query($conn, $trends_query);

$trend_dates = [];
$trend_satisfaction = [];
$trend_volume = [];

while ($row = mysqli_fetch_assoc($trends_result)) {
    $trend_dates[] = $row['log_date'];
    $trend_satisfaction[] = round($row['daily_satisfaction'], 1);
    $trend_volume[] = $row['total_ratings'];
}

// Fetch Rating Distribution (Likes vs Dislikes)
$dist_query = "SELECT 
    CASE WHEN rating = 1 THEN 'Likes (Positive)' ELSE 'Dislikes (Negative)' END as rating_type,
    COUNT(*) as count
    FROM recommendation_ratings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY rating";
$dist_result = mysqli_query($conn, $dist_query);

$dist_labels = [];
$dist_values = [];

while ($row = mysqli_fetch_assoc($dist_result)) {
    $dist_labels[] = $row['rating_type'];
    $dist_values[] = $row['count'];
}

// Fetch Top Rated Products
$top_products_query = "SELECT 
    p.product_name,
    COUNT(r.rating_id) as rating_count,
    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as likes
    FROM recommendation_ratings r
    JOIN products p ON r.product_id = p.product_id
    GROUP BY p.product_id
    ORDER BY likes DESC
    LIMIT 5";
$top_products_result = mysqli_query($conn, $top_products_query);

// ===================== VIEW SECTION =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Performance Analytics - Smart Laptop Advisor Admin</title>
    
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
                <h3>AI Performance Analytics</h3>
                <p class="text-subtitle text-muted">Monitor user satisfaction and recommendation engine performance</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">AI Engine</li>
                        <li class="breadcrumb-item active" aria-current="page">Performance</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon purple">
                                <i class="iconly-boldHeart"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Satisfaction Score</h6>
                            <h6 class="font-extrabold mb-0"><?php echo round($satisfaction_score, 1); ?>%</h6>
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
                                <i class="iconly-boldChat"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Feedback</h6>
                            <h6 class="font-extrabold mb-0"><?php echo $kpis['total_ratings']; ?></h6>
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
                                <i class="iconly-boldTick-Square"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Likes</h6>
                            <h6 class="font-extrabold mb-0"><?php echo $kpis['total_likes']; ?></h6>
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
                                <i class="iconly-boldClose-Square"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Dislikes</h6>
                            <h6 class="font-extrabold mb-0"><?php echo $kpis['total_dislikes']; ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Performance Trends Chart -->
    <section class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Satisfaction Trends (Last 7 Days)</h4>
                </div>
                <div class="card-body">
                    <div id="performanceTrendsChart"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Rating Distribution & Performance by Persona -->
    <section class="row">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>Feedback Distribution</h4>
                </div>
                <div class="card-body">
                    <div id="confidenceDistributionChart"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>Performance by Persona</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Persona</th>
                                    <th>Feedback Vol.</th>
                                    <th>Likes</th>
                                    <th>Satisfaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($persona_perf_result && mysqli_num_rows($persona_perf_result) > 0):
                                    while ($persona = mysqli_fetch_assoc($persona_perf_result)):
                                        // Skip if no ratings and we want to keep the table clean, 
                                        // or show 0s. Let's show 0s but maybe filter out completely empty ones if needed.
                                        // For now, showing all personas is good for visibility.
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light-<?php echo $persona['color_theme']; ?>">
                                            <?php echo htmlspecialchars($persona['persona_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($persona['total_ratings']); ?></td>
                                    <td><?php echo number_format($persona['total_likes']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $persona['satisfaction_score'] >= 70 ? 'success' : ($persona['satisfaction_score'] >= 40 ? 'warning' : 'danger'); ?>" 
                                                 style="width: <?php echo round($persona['satisfaction_score']); ?>%">
                                                <?php echo round($persona['satisfaction_score'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No performance data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Top Rated Products -->
    <section class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Top Rated Recommended Products</h4>
                    <p class="text-muted mb-0">Products with the most positive user feedback</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Total Ratings</th>
                                    <th>Total Likes</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($top_products_result && mysqli_num_rows($top_products_result) > 0):
                                    while ($prod = mysqli_fetch_assoc($top_products_result)):
                                        $approval_rate = ($prod['likes'] / $prod['rating_count']) * 100;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['product_name']); ?></td>
                                    <td><?php echo $prod['rating_count']; ?></td>
                                    <td><?php echo $prod['likes']; ?></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo round($approval_rate, 1); ?>%</span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center">No ratings recorded yet.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Optimization Recommendations -->
    <section class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>System Insights</h4>
                </div>
                <div class="card-body">
                    <?php if ($satisfaction_score < 70): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention Needed:</strong> Overall user satisfaction is below 70%. Consider adjusting persona weightages.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Good Performance:</strong> User satisfaction is healthy (>70%).
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Insight:</strong> You have received <?php echo $kpis['total_ratings']; ?> user ratings in the last 30 days.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Performance Trends Chart
    const trendChartOptions = {
        series: [{
            name: 'Satisfaction Score (%)',
            data: <?php echo json_encode($trend_satisfaction); ?>
        }, {
            name: 'Feedback Volume',
            data: <?php echo json_encode($trend_volume); ?>
        }],
        chart: {
            type: 'line',
            height: 350
        },
        xaxis: {
            categories: <?php echo json_encode($trend_dates); ?>
        },
        yaxis: [{
            title: {
                text: 'Satisfaction (%)'
            },
            max: 100
        }, {
            opposite: true,
            title: {
                text: 'Volume'
            }
        }],
        stroke: {
            width: [3, 2],
            curve: 'smooth',
            dashArray: [0, 5]
        },
        colors: ['#435ebe', '#9694ff']
    };
    const trendChart = new ApexCharts(document.querySelector("#performanceTrendsChart"), trendChartOptions);
    trendChart.render();

    // Confidence Distribution Chart (Now Feedback Distribution)
    const confidenceChartOptions = {
        series: <?php echo json_encode($dist_values); ?>,
        chart: {
            type: 'donut',
            height: 350
        },
        labels: <?php echo json_encode($dist_labels); ?>,
        colors: ['#28a745', '#dc3545'], // Green for Likes, Red for Dislikes
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => {
                                    return a + b
                                }, 0)
                            }
                        }
                    }
                }
            }
        }
    };
    const confidenceChart = new ApexCharts(document.querySelector("#confidenceDistributionChart"), confidenceChartOptions);
    confidenceChart.render();
</script>

<?php
include 'includes/admin_footer.php';
?>
        </div>
    </div>
</body>
</html>
