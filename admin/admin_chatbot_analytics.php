<?php
// ============================================
// Chatbot Analytics - Chatbot Management
// Module D: Smart Laptop Advisor Admin
// ============================================

// Include database connection
require_once 'includes/db_connect.php';

// ============================================
// LOGIC SECTION - Data Fetching
// ============================================

// Fetch today's analytics
$today_query = "SELECT * FROM chatbot_analytics 
                WHERE date = CURDATE()";
$today_result = $conn->query($today_query);
$today_analytics = $today_result->fetch_assoc();

// If no data for today, use latest available
if (!$today_analytics) {
    $today_query = "SELECT * FROM chatbot_analytics 
                    ORDER BY date DESC LIMIT 1";
    $today_result = $conn->query($today_query);
    $today_analytics = $today_result->fetch_assoc();
}

// Fetch 7-day trend data
$trend_query = "SELECT 
    date,
    total_conversations,
    total_messages,
    avg_messages_per_session,
    avg_response_time_ms,
    intent_accuracy,
    resolution_rate,
    satisfaction_score
FROM chatbot_analytics 
WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY date ASC";
$trend_result = $conn->query($trend_query);
$trend_data = [];
while ($row = $trend_result->fetch_assoc()) {
    $trend_data[] = $row;
}

// Fetch top intents by usage
$top_intents_query = "SELECT 
    i.intent_name,
    i.display_name,
    i.usage_count,
    i.success_count,
    CASE WHEN i.usage_count > 0 THEN (i.success_count / i.usage_count) * 100 ELSE 0 END as success_rate
FROM intents i
WHERE i.usage_count > 0
ORDER BY i.usage_count DESC
LIMIT 10";
$top_intents_result = $conn->query($top_intents_query);
$top_intents = [];
while ($row = $top_intents_result->fetch_assoc()) {
    $top_intents[] = $row;
}

// Fetch recent unrecognized queries
$unrecognized_query = "SELECT 
    cm.message_content,
    cm.timestamp,
    COUNT(*) as occurrences
FROM conversation_messages cm
WHERE cm.message_type = 'user' 
AND cm.intent_detected IS NULL
AND cm.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY cm.message_content
ORDER BY occurrences DESC
LIMIT 10";
$unrecognized_result = $conn->query($unrecognized_query);
$unrecognized_queries = [];
while ($row = $unrecognized_result->fetch_assoc()) {
    $unrecognized_queries[] = $row;
}

// Prepare chart data
$chart_dates = [];
$chart_conversations = [];
$chart_accuracy = [];
$chart_satisfaction = [];

foreach ($trend_data as $day) {
    $chart_dates[] = date('M d', strtotime($day['date']));
    $chart_conversations[] = $day['total_conversations'];
    $chart_accuracy[] = round($day['intent_accuracy'], 1);
    $chart_satisfaction[] = round($day['satisfaction_score'], 1);
}

// ============================================
// VIEW SECTION - HTML Output
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Analytics - Smart Laptop Advisor Admin</title>
    
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
                <h3>Chatbot Analytics</h3>
                <p class="text-subtitle text-muted">Monitor and analyze chatbot performance metrics</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Chatbot</li>
                        <li class="breadcrumb-item active" aria-current="page">Analytics</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon purple">
                                <i class="iconly-boldChat"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Conversations</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($today_analytics['total_conversations'] ?? 0); ?></h6>
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
                                <i class="iconly-boldActivity"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Intent Accuracy</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($today_analytics['intent_accuracy'] ?? 0, 1); ?>%</h6>
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
                                <i class="iconly-boldTicket"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Resolution Rate</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($today_analytics['resolution_rate'] ?? 0, 1); ?>%</h6>
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
                                <i class="iconly-boldStar"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Satisfaction</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($today_analytics['satisfaction_score'] ?? 0, 1); ?>/5.0</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Conversation Trends Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4>Conversation Trends (Last 7 Days)</h4>
                </div>
                <div class="card-body">
                    <canvas id="conversationTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Sentiment Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4>Sentiment Distribution</h4>
                </div>
                <div class="card-body">
                    <canvas id="sentimentChart"></canvas>
                    <div class="mt-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-circle-fill text-success me-2"></i>Positive</span>
                            <strong><?php echo number_format($today_analytics['positive_sentiment_pct'] ?? 0, 1); ?>%</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-circle-fill text-secondary me-2"></i>Neutral</span>
                            <strong><?php echo number_format($today_analytics['neutral_sentiment_pct'] ?? 0, 1); ?>%</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="bi bi-circle-fill text-danger me-2"></i>Negative</span>
                            <strong><?php echo number_format($today_analytics['negative_sentiment_pct'] ?? 0, 1); ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Intent Accuracy & Response Time -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>Intent Accuracy Over Time</h4>
                </div>
                <div class="card-body">
                    <canvas id="accuracyChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>Satisfaction Rating Trend</h4>
                </div>
                <div class="card-body">
                    <canvas id="satisfactionChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Intents & Unrecognized Queries -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>Top Performing Intents</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Intent</th>
                                    <th>Usage</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_intents as $intent): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($intent['display_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($intent['intent_name']); ?></small>
                                        </td>
                                        <td><?php echo number_format($intent['usage_count']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $intent['success_rate'] >= 90 ? 'success' : ($intent['success_rate'] >= 70 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($intent['success_rate'], 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Unrecognized Queries</h4>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportUnrecognized()">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Occurrences</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($unrecognized_queries) > 0): ?>
                                    <?php foreach ($unrecognized_queries as $query): ?>
                                        <tr>
                                            <td>
                                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo htmlspecialchars($query['message_content']); ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-danger"><?php echo $query['occurrences']; ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-success" onclick="createIntent('<?php echo htmlspecialchars($query['message_content'], ENT_QUOTES); ?>')">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No unrecognized queries found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Summary -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Performance Summary</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h6 class="text-muted">Avg Messages/Session</h6>
                            <h4><?php echo number_format($today_analytics['avg_messages_per_session'] ?? 0, 1); ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted">Avg Response Time</h6>
                            <h4><?php echo number_format($today_analytics['avg_response_time_ms'] ?? 0); ?>ms</h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted">Total Messages</h6>
                            <h4><?php echo number_format($today_analytics['total_messages'] ?? 0); ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted">Unrecognized</h6>
                            <h4 class="text-danger"><?php echo number_format($today_analytics['unrecognized_intent_count'] ?? 0); ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted">Fallback Count</h6>
                            <h4 class="text-warning"><?php echo number_format($today_analytics['fallback_count'] ?? 0); ?></h4>
                        </div>
                        <div class="col-md-2">
                            <h6 class="text-muted">Intent Accuracy</h6>
                            <h4 class="text-success"><?php echo number_format($today_analytics['intent_accuracy'] ?? 0, 1); ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}
.stats-icon.purple { background: #7367f0; }
.stats-icon.blue { background: #00cfe8; }
.stats-icon.green { background: #28c76f; }
.stats-icon.red { background: #ea5455; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Conversation Trend Chart
const conversationTrendCtx = document.getElementById('conversationTrendChart').getContext('2d');
new Chart(conversationTrendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_dates); ?>,
        datasets: [{
            label: 'Conversations',
            data: <?php echo json_encode($chart_conversations); ?>,
            borderColor: '#7367f0',
            backgroundColor: 'rgba(115, 103, 240, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Sentiment Distribution Pie Chart
const sentimentCtx = document.getElementById('sentimentChart').getContext('2d');
new Chart(sentimentCtx, {
    type: 'doughnut',
    data: {
        labels: ['Positive', 'Neutral', 'Negative'],
        datasets: [{
            data: [
                <?php echo $today_analytics['positive_sentiment_pct'] ?? 0; ?>,
                <?php echo $today_analytics['neutral_sentiment_pct'] ?? 0; ?>,
                <?php echo $today_analytics['negative_sentiment_pct'] ?? 0; ?>
            ],
            backgroundColor: ['#28c76f', '#6c757d', '#ea5455'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Intent Accuracy Chart
const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
new Chart(accuracyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_dates); ?>,
        datasets: [{
            label: 'Intent Accuracy (%)',
            data: <?php echo json_encode($chart_accuracy); ?>,
            borderColor: '#00cfe8',
            backgroundColor: 'rgba(0, 207, 232, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true, max: 100 }
        }
    }
});

// Satisfaction Rating Chart
const satisfactionCtx = document.getElementById('satisfactionChart').getContext('2d');
new Chart(satisfactionCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_dates); ?>,
        datasets: [{
            label: 'Satisfaction Score',
            data: <?php echo json_encode($chart_satisfaction); ?>,
            borderColor: '#28c76f',
            backgroundColor: 'rgba(40, 199, 111, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true, max: 5 }
        }
    }
});

function createIntent(query) {
    window.location.href = 'admin_intent_management.php?create=' + encodeURIComponent(query);
}

function exportUnrecognized() {
    window.location.href = 'ajax/export_unrecognized_queries.php';
}
</script>

<?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>
</body>
</html>
