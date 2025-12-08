<?php
// ============================================
// Chatbot Analytics - Enhanced ASUS-Style
// Module D: Smart Laptop Advisor Admin
// ============================================

require_once 'includes/db_connect.php';

date_default_timezone_set('Asia/Singapore');

// ============================================
// LOGIC SECTION
// ============================================

// Helper to get last 7 days
$dates = [];
for ($i = 6; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

// 1. Today's Analytics
$today_stats_query = "SELECT 
    COUNT(DISTINCT c.conversation_id) as total_conversations,
    COUNT(cm.message_id) as total_messages,
    AVG(cm.response_time_ms) as avg_response_time_ms,
    (SELECT COUNT(*) FROM conversation_messages WHERE intent_detected IS NULL AND message_type = 'user' AND DATE(timestamp) = CURDATE()) as unrecognized_count,
    (SELECT COUNT(*) FROM conversation_messages WHERE intent_detected = 'fallback' AND DATE(timestamp) = CURDATE()) as fallback_count,
    (SELECT AVG(satisfaction_rating) FROM conversations WHERE satisfaction_rating IS NOT NULL AND DATE(started_at) = CURDATE()) as satisfaction_score
FROM conversations c
LEFT JOIN conversation_messages cm ON c.conversation_id = cm.conversation_id AND DATE(cm.timestamp) = CURDATE()
WHERE DATE(c.started_at) = CURDATE()";

$today_result = $conn->query($today_stats_query);
$today_analytics = $today_result->fetch_assoc();

$today_analytics['avg_messages_per_session'] = $today_analytics['total_conversations'] > 0 
    ? $today_analytics['total_messages'] / $today_analytics['total_conversations'] 
    : 0;

// Intent Accuracy
$accuracy_query = "SELECT 
    (SUM(CASE WHEN intent_confidence >= 0.7 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as accuracy
FROM conversation_messages 
WHERE message_type = 'user' AND DATE(timestamp) = CURDATE() AND intent_detected IS NOT NULL";
$accuracy_result = $conn->query($accuracy_query);
$accuracy_data = $accuracy_result->fetch_assoc();
$today_analytics['intent_accuracy'] = $accuracy_data['accuracy'] ?? 0;

// Sentiment Distribution
$sentiment_query = "SELECT sentiment, COUNT(*) as count FROM conversations WHERE DATE(started_at) = CURDATE() GROUP BY sentiment";
$sentiment_result = $conn->query($sentiment_query);
$sentiments = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
$total_sentiments = 0;
while ($row = $sentiment_result->fetch_assoc()) {
    $sent = strtolower($row['sentiment'] ?? 'neutral');
    if (isset($sentiments[$sent])) {
        $sentiments[$sent] = $row['count'];
        $total_sentiments += $row['count'];
    }
}

$today_analytics['positive_sentiment_pct'] = $total_sentiments > 0 ? ($sentiments['positive'] / $total_sentiments) * 100 : 0;
$today_analytics['neutral_sentiment_pct'] = $total_sentiments > 0 ? ($sentiments['neutral'] / $total_sentiments) * 100 : 0;
$today_analytics['negative_sentiment_pct'] = $total_sentiments > 0 ? ($sentiments['negative'] / $total_sentiments) * 100 : 0;

// Resolution Rate
$resolution_query = "SELECT 
    (SUM(CASE WHEN outcome IN ('recommendation_made', 'order_placed') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as resolution_rate
FROM conversations WHERE DATE(started_at) = CURDATE()";
$resolution_result = $conn->query($resolution_query);
$resolution_data = $resolution_result->fetch_assoc();
$today_analytics['resolution_rate'] = $resolution_data['resolution_rate'] ?? 0;

// Leads Captured
$leads_query = "SELECT COUNT(DISTINCT conversation_id) as leads_count 
FROM conversations WHERE customer_email IS NOT NULL AND DATE(started_at) = CURDATE()";
$leads_result = $conn->query($leads_query);
$leads_data = $leads_result->fetch_assoc();
$today_analytics['leads_count'] = $leads_data['leads_count'] ?? 0;

// All-time leads
$all_leads_query = "SELECT COUNT(DISTINCT conversation_id) as total_leads FROM conversations WHERE customer_email IS NOT NULL";
$all_leads_result = $conn->query($all_leads_query);
$all_leads_data = $all_leads_result->fetch_assoc();
$total_leads = $all_leads_data['total_leads'] ?? 0;

// 2. 7-day trend data
$trend_data = [];
foreach ($dates as $date) {
    $day_query = "SELECT COUNT(*) as total_conversations, AVG(satisfaction_rating) as satisfaction_score
    FROM conversations WHERE DATE(started_at) = '$date'";
    $day_result = $conn->query($day_query);
    $day_data = $day_result->fetch_assoc();
    
    $acc_query = "SELECT (SUM(CASE WHEN intent_confidence >= 0.7 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as accuracy
    FROM conversation_messages WHERE message_type = 'user' AND DATE(timestamp) = '$date' AND intent_detected IS NOT NULL";
    $acc_result = $conn->query($acc_query);
    $acc_data = $acc_result->fetch_assoc();
    
    $leads_day_query = "SELECT COUNT(DISTINCT conversation_id) as leads FROM conversations WHERE customer_email IS NOT NULL AND DATE(started_at) = '$date'";
    $leads_day_result = $conn->query($leads_day_query);
    $leads_day_data = $leads_day_result->fetch_assoc();
    
    $trend_data[] = [
        'date' => $date,
        'total_conversations' => $day_data['total_conversations'] ?? 0,
        'satisfaction_score' => $day_data['satisfaction_score'] ?? 0,
        'intent_accuracy' => $acc_data['accuracy'] ?? 0,
        'leads' => $leads_day_data['leads'] ?? 0
    ];
}

// 3. Top Intents
$top_intents_query = "SELECT 
    intent_detected as intent_name,
    COUNT(*) as usage_count,
    AVG(intent_confidence) * 100 as success_rate
FROM conversation_messages
WHERE message_type = 'user' AND intent_detected IS NOT NULL
GROUP BY intent_detected ORDER BY usage_count DESC LIMIT 8";
$top_intents_result = $conn->query($top_intents_query);
$top_intents = [];
while ($row = $top_intents_result->fetch_assoc()) {
    $row['display_name'] = ucwords(str_replace('_', ' ', $row['intent_name']));
    $top_intents[] = $row;
}

// 4. Unrecognized Queries
$unrecognized_query = "SELECT message_content, timestamp, COUNT(*) as occurrences
FROM conversation_messages
WHERE message_type = 'user' AND intent_detected IS NULL AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY message_content ORDER BY occurrences DESC LIMIT 8";
$unrecognized_result = $conn->query($unrecognized_query);
$unrecognized_queries = [];
while ($row = $unrecognized_result->fetch_assoc()) {
    $unrecognized_queries[] = $row;
}

// 5. Popular Products Mentioned (simplified to avoid collation issues)
$popular_products = [];
// Query commented out due to collation mismatch between tables
// This feature can be re-enabled after fixing database collations

// Chart data
$chart_dates = [];
$chart_conversations = [];
$chart_accuracy = [];
$chart_satisfaction = [];
$chart_leads = [];

foreach ($trend_data as $day) {
    $chart_dates[] = date('M d', strtotime($day['date']));
    $chart_conversations[] = $day['total_conversations'];
    $chart_accuracy[] = round($day['intent_accuracy'], 1);
    $chart_satisfaction[] = round($day['satisfaction_score'], 1);
    $chart_leads[] = $day['leads'];
}

// Calculate max intent usage for progress bars
$max_intent_usage = !empty($top_intents) ? max(array_column($top_intents, 'usage_count')) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Analytics - Smart Laptop Advisor</title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="source/assets/css/bootstrap.css">
    <link rel="stylesheet" href="source/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="source/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="source/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="source/assets/css/app.css">
    <link rel="shortcut icon" href="source/assets/images/favicon.svg" type="image/x-icon">
    
    <style>
    :root {
        --asus-primary: #0d6efd;
        --asus-secondary: #6c63ff;
        --asus-success: #10b981;
        --asus-warning: #f59e0b;
        --asus-danger: #ef4444;
        --asus-info: #06b6d4;
        --asus-dark: #1e293b;
        --asus-light: #f8fafc;
        --asus-gradient: linear-gradient(135deg, #0d6efd 0%, #6c63ff 100%);
    }
    
    /* Page Header */
    .analytics-header {
        background: var(--asus-gradient);
        color: white;
        padding: 30px;
        border-radius: 20px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .analytics-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .analytics-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: 20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    
    .analytics-header h3 {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 8px;
        position: relative;
        z-index: 1;
    }
    
    .analytics-header p {
        opacity: 0.9;
        margin-bottom: 0;
        position: relative;
        z-index: 1;
    }
    
    /* Metric Cards */
    .metric-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    
    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    
    .metric-card .metric-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        margin-bottom: 16px;
    }
    
    .metric-card .metric-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--asus-dark);
        line-height: 1;
        margin-bottom: 4px;
    }
    
    .metric-card .metric-label {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .metric-card .metric-trend {
        position: absolute;
        top: 20px;
        right: 20px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .metric-trend.up { background: rgba(16, 185, 129, 0.1); color: var(--asus-success); }
    .metric-trend.down { background: rgba(239, 68, 68, 0.1); color: var(--asus-danger); }
    
    /* Chart Cards */
    .chart-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .chart-card .card-header {
        background: transparent;
        border-bottom: 1px solid #e2e8f0;
        padding: 20px 24px;
    }
    
    .chart-card .card-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--asus-dark);
        margin: 0;
    }
    
    .chart-card .card-body {
        padding: 24px;
    }
    
    /* Sentiment Donut */
    .sentiment-stats {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-top: 20px;
    }
    
    .sentiment-item {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sentiment-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    
    .sentiment-label {
        flex: 1;
        font-size: 0.9rem;
        color: #64748b;
    }
    
    .sentiment-value {
        font-weight: 700;
        color: var(--asus-dark);
    }
    
    /* Intent List */
    .intent-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .intent-item {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .intent-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }
    
    .intent-info {
        flex: 1;
        min-width: 0;
    }
    
    .intent-name {
        font-weight: 700;
        color: var(--asus-dark);
        font-size: 0.9rem;
        margin-bottom: 4px;
    }
    
    .intent-progress {
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        overflow: hidden;
    }
    
    .intent-progress-bar {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s ease;
    }
    
    .intent-usage {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        white-space: nowrap;
    }
    
    .intent-rate {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    
    /* Unrecognized Queries */
    .query-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: var(--asus-light);
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.2s ease;
    }
    
    .query-item:hover {
        background: #e2e8f0;
    }
    
    .query-text {
        flex: 1;
        font-size: 0.9rem;
        color: var(--asus-dark);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .query-count {
        padding: 4px 12px;
        background: rgba(239, 68, 68, 0.1);
        color: var(--asus-danger);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    
    .query-action {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        background: white;
        color: var(--asus-success);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .query-action:hover {
        background: var(--asus-success);
        border-color: var(--asus-success);
        color: white;
    }
    
    /* Performance Grid */
    .performance-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 24px;
        padding: 24px;
    }
    
    .perf-item {
        text-align: center;
    }
    
    .perf-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--asus-dark);
        margin-bottom: 4px;
    }
    
    .perf-label {
        font-size: 0.8rem;
        color: #64748b;
    }
    
    /* Live Chat Preview */
    .live-preview-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .live-preview-header {
        background: var(--asus-gradient);
        color: white;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .live-preview-header .avatar {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    
    .live-preview-header .status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        opacity: 0.9;
    }
    
    .live-preview-header .status-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .live-preview-body {
        height: 300px;
        background: #f8fafc;
        padding: 20px;
        overflow-y: auto;
    }
    
    .preview-message {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
    }
    
    .preview-message.bot {
        align-items: flex-start;
    }
    
    .preview-message.user {
        align-items: flex-end;
    }
    
    .preview-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 0.9rem;
    }
    
    .preview-message.bot .preview-bubble {
        background: white;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    
    .preview-message.user .preview-bubble {
        background: var(--asus-gradient);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .performance-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .metric-card .metric-value {
            font-size: 2rem;
        }
    }
    </style>
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

            <!-- Analytics Header -->
            <div class="analytics-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><i class="bi bi-graph-up-arrow me-2"></i>Chatbot Analytics</h3>
                        <p>Real-time performance metrics and insights</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge bg-light text-dark me-2">
                            <i class="bi bi-calendar me-1"></i>Last 7 Days
                        </span>
                        <button class="btn btn-light btn-sm" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: var(--asus-gradient);">
                            <i class="bi bi-chat-dots-fill"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($today_analytics['total_conversations'] ?? 0); ?></div>
                        <div class="metric-label">Today's Conversations</div>
                        <span class="metric-trend up"><i class="bi bi-arrow-up"></i> Active</span>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                            <i class="bi bi-bullseye"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($today_analytics['intent_accuracy'] ?? 0, 0); ?>%</div>
                        <div class="metric-label">Intent Accuracy</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($today_analytics['resolution_rate'] ?? 0, 0); ?>%</div>
                        <div class="metric-label">Resolution Rate</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="bi bi-envelope-check-fill"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($today_analytics['leads_count'] ?? 0); ?></div>
                        <div class="metric-label">Leads Captured Today</div>
                        <span class="metric-trend up"><?php echo $total_leads; ?> Total</span>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="chart-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bi bi-graph-up me-2"></i>Conversation Trends</h5>
                            <small class="text-muted">Last 7 Days</small>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-emoji-smile me-2"></i>Sentiment Analysis</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="sentimentChart"></canvas>
                            <div class="sentiment-stats">
                                <div class="sentiment-item">
                                    <div class="sentiment-dot" style="background: #10b981;"></div>
                                    <span class="sentiment-label">Positive</span>
                                    <span class="sentiment-value"><?php echo number_format($today_analytics['positive_sentiment_pct'], 0); ?>%</span>
                                </div>
                                <div class="sentiment-item">
                                    <div class="sentiment-dot" style="background: #64748b;"></div>
                                    <span class="sentiment-label">Neutral</span>
                                    <span class="sentiment-value"><?php echo number_format($today_analytics['neutral_sentiment_pct'], 0); ?>%</span>
                                </div>
                                <div class="sentiment-item">
                                    <div class="sentiment-dot" style="background: #ef4444;"></div>
                                    <span class="sentiment-label">Negative</span>
                                    <span class="sentiment-value"><?php echo number_format($today_analytics['negative_sentiment_pct'], 0); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Intents & Queries Row -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="chart-card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-lightning-charge me-2"></i>Top Performing Intents</h5>
                        </div>
                        <div class="card-body">
                            <div class="intent-list">
                                <?php 
                                $intent_colors = ['#0d6efd', '#6c63ff', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899'];
                                $i = 0;
                                foreach ($top_intents as $intent): 
                                    $color = $intent_colors[$i % count($intent_colors)];
                                    $progress = ($intent['usage_count'] / $max_intent_usage) * 100;
                                    $rate_class = $intent['success_rate'] >= 80 ? 'bg-success text-white' : ($intent['success_rate'] >= 60 ? 'bg-warning' : 'bg-danger text-white');
                                ?>
                                <div class="intent-item">
                                    <div class="intent-icon" style="background: <?php echo $color; ?>;">
                                        <i class="bi bi-lightning"></i>
                                    </div>
                                    <div class="intent-info">
                                        <div class="intent-name"><?php echo htmlspecialchars($intent['display_name']); ?></div>
                                        <div class="intent-progress">
                                            <div class="intent-progress-bar" style="width: <?php echo $progress; ?>%; background: <?php echo $color; ?>;"></div>
                                        </div>
                                    </div>
                                    <div class="intent-usage"><?php echo number_format($intent['usage_count']); ?></div>
                                    <span class="intent-rate <?php echo $rate_class; ?>"><?php echo number_format($intent['success_rate'], 0); ?>%</span>
                                </div>
                                <?php $i++; endforeach; ?>
                                <?php if (empty($top_intents)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2 mb-0">No intent data available</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bi bi-question-circle me-2"></i>Unrecognized Queries</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportUnrecognized()">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($unrecognized_queries)): ?>
                                <?php foreach ($unrecognized_queries as $query): ?>
                                <div class="query-item">
                                    <div class="query-text"><?php echo htmlspecialchars($query['message_content']); ?></div>
                                    <span class="query-count"><?php echo $query['occurrences']; ?>x</span>
                                    <button class="query-action" onclick="trainQuery('<?php echo htmlspecialchars($query['message_content'], ENT_QUOTES); ?>')" title="Add Training">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 text-success"></i>
                                    <p class="mt-2 mb-0">All queries recognized!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary & Live Preview -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-speedometer2 me-2"></i>Performance Summary</h5>
                        </div>
                        <div class="performance-grid">
                            <div class="perf-item">
                                <div class="perf-value"><?php echo number_format($today_analytics['avg_messages_per_session'], 1); ?></div>
                                <div class="perf-label">Avg Messages</div>
                            </div>
                            <div class="perf-item">
                                <div class="perf-value"><?php echo number_format($today_analytics['avg_response_time_ms'] ?? 0); ?>ms</div>
                                <div class="perf-label">Avg Response</div>
                            </div>
                            <div class="perf-item">
                                <div class="perf-value"><?php echo number_format($today_analytics['total_messages'] ?? 0); ?></div>
                                <div class="perf-label">Total Messages</div>
                            </div>
                            <div class="perf-item">
                                <div class="perf-value text-danger"><?php echo number_format($today_analytics['unrecognized_count'] ?? 0); ?></div>
                                <div class="perf-label">Unrecognized</div>
                            </div>
                            <div class="perf-item">
                                <div class="perf-value text-warning"><?php echo number_format($today_analytics['fallback_count'] ?? 0); ?></div>
                                <div class="perf-label">Fallbacks</div>
                            </div>
                            <div class="perf-item">
                                <div class="perf-value text-success"><?php echo number_format($today_analytics['satisfaction_score'] ?? 0, 1); ?>/5</div>
                                <div class="perf-label">Satisfaction</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="live-preview-card">
                        <div class="live-preview-header">
                            <div class="avatar">🤖</div>
                            <div>
                                <div class="fw-bold">AI Assistant</div>
                                <div class="status">
                                    <div class="status-dot"></div>
                                    Live Preview
                                </div>
                            </div>
                        </div>
                        <div class="live-preview-body">
                            <div class="preview-message bot">
                                <div class="preview-bubble">
                                    Hi! 👋 I'm your Smart Laptop Advisor. How can I help you today?
                                </div>
                            </div>
                            <div class="preview-message user">
                                <div class="preview-bubble">
                                    I need a gaming laptop under RM5000
                                </div>
                            </div>
                            <div class="preview-message bot">
                                <div class="preview-bubble">
                                    Great choice! Here are some top gaming laptops within your budget...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accuracy Trend -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="chart-card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-bullseye me-2"></i>Intent Accuracy Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="accuracyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="chart-card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-envelope-plus me-2"></i>Leads Captured Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="leadsChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
    // Chart.js Global Defaults
    Chart.defaults.font.family = 'Nunito, sans-serif';
    Chart.defaults.font.size = 12;
    
    // Conversation Trend Chart
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_dates); ?>,
            datasets: [{
                label: 'Conversations',
                data: <?php echo json_encode($chart_conversations); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { color: '#e2e8f0' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    
    // Sentiment Chart
    new Chart(document.getElementById('sentimentChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Positive', 'Neutral', 'Negative'],
            datasets: [{
                data: [
                    <?php echo $today_analytics['positive_sentiment_pct']; ?>,
                    <?php echo $today_analytics['neutral_sentiment_pct']; ?>,
                    <?php echo $today_analytics['negative_sentiment_pct']; ?>
                ],
                backgroundColor: ['#10b981', '#64748b', '#ef4444'],
                borderWidth: 0,
                cutout: '75%'
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
    
    // Accuracy Chart
    new Chart(document.getElementById('accuracyChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_dates); ?>,
            datasets: [{
                label: 'Accuracy %',
                data: <?php echo json_encode($chart_accuracy); ?>,
                borderColor: '#06b6d4',
                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#e2e8f0' } },
                x: { grid: { display: false } }
            }
        }
    });
    
    // Leads Chart
    new Chart(document.getElementById('leadsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_dates); ?>,
            datasets: [{
                label: 'Leads',
                data: <?php echo json_encode($chart_leads); ?>,
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: '#f59e0b',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                x: { grid: { display: false } }
            }
        }
    });
    
    function trainQuery(query) {
        alert('Training feature coming soon!\nQuery: ' + query);
    }
    
    function exportUnrecognized() {
        window.location.href = 'ajax/export_unrecognized_queries.php';
    }
    </script>
</body>
</html>
