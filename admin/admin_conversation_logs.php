<?php
// ============================================
// Conversation Logs - Enhanced ASUS-Style
// Module D: Smart Laptop Advisor Admin
// ============================================

require_once 'includes/db_connect.php';

// ============================================
// LOGIC SECTION
// ============================================

// Fetch conversation statistics
$stats_query = "SELECT 
    COUNT(*) as total_conversations,
    COUNT(CASE WHEN DATE(started_at) = CURDATE() THEN 1 END) as today_count,
    COUNT(CASE WHEN DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_count,
    AVG(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) * 100 as satisfaction_rate,
    AVG(message_count) as avg_messages_per_session,
    AVG(duration_seconds) as avg_duration
FROM conversations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Handle filters
$sentiment_filter = isset($_GET['sentiment']) ? $_GET['sentiment'] : 'all';
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($sentiment_filter !== 'all') {
    $where_conditions[] = "c.sentiment = ?";
    $params[] = $sentiment_filter;
    $types .= 's';
}

if ($time_filter !== 'all') {
    switch ($time_filter) {
        case 'today':
            $where_conditions[] = "DATE(c.started_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "c.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "c.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search_term)) {
    $where_conditions[] = "(c.session_id LIKE ? OR u.full_name LIKE ? OR c.user_ip LIKE ? OR c.customer_email LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch conversations
$conversations_query = "SELECT 
    c.conversation_id,
    c.session_id,
    c.source,
    c.started_at,
    c.duration_seconds,
    c.message_count,
    c.user_message_count,
    c.bot_message_count,
    c.sentiment,
    c.outcome,
    c.satisfaction_rating,
    c.customer_email,
    COALESCE(u.full_name, 'Anonymous User') as user_name,
    COALESCE(u.email, c.user_ip) as user_identifier
FROM conversations c
LEFT JOIN users u ON c.user_id = u.user_id
{$where_clause}
ORDER BY c.started_at DESC
LIMIT 50";

if (!empty($params)) {
    $stmt = $conn->prepare($conversations_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $conversations_result = $stmt->get_result();
} else {
    $conversations_result = $conn->query($conversations_query);
}

$conversations = [];
while ($row = $conversations_result->fetch_assoc()) {
    $conversations[] = $row;
}

// Get hourly distribution for today
$hourly_query = "SELECT HOUR(started_at) as hour, COUNT(*) as count 
                 FROM conversations 
                 WHERE DATE(started_at) = CURDATE() 
                 GROUP BY HOUR(started_at)";
$hourly_result = $conn->query($hourly_query);
$hourly_data = array_fill(0, 24, 0);
while ($row = $hourly_result->fetch_assoc()) {
    $hourly_data[$row['hour']] = $row['count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation Logs - Smart Laptop Advisor</title>
    
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
        --asus-dark: #1e293b;
        --asus-light: #f8fafc;
        --asus-gradient: linear-gradient(135deg, #0d6efd 0%, #6c63ff 100%);
    }
    
    /* Stats Cards - ASUS Style */
    .stats-card {
        background: var(--asus-light);
        border-radius: 16px;
        padding: 24px;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stats-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    
    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--asus-gradient);
    }
    
    .stats-card .stats-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    
    .stats-card .stats-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--asus-dark);
        line-height: 1;
    }
    
    .stats-card .stats-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        margin-top: 4px;
    }
    
    .stats-card .stats-change {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 20px;
        font-weight: 600;
    }
    
    .stats-change.positive { background: rgba(16, 185, 129, 0.1); color: var(--asus-success); }
    .stats-change.negative { background: rgba(239, 68, 68, 0.1); color: var(--asus-danger); }
    
    /* Conversation Table */
    .conversation-table {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    
    .conversation-table .table {
        margin-bottom: 0;
    }
    
    .conversation-table thead {
        background: var(--asus-light);
    }
    
    .conversation-table thead th {
        border: none;
        font-weight: 700;
        color: var(--asus-dark);
        padding: 16px 20px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .conversation-table tbody tr {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .conversation-table tbody tr:hover {
        background: #f1f5f9;
    }
    
    .conversation-table tbody td {
        padding: 16px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
    }
    
    /* User Avatar */
    .user-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        color: white;
    }
    
    /* Sentiment Badge */
    .sentiment-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .sentiment-badge.positive { background: rgba(16, 185, 129, 0.1); color: var(--asus-success); }
    .sentiment-badge.neutral { background: rgba(100, 116, 139, 0.1); color: #64748b; }
    .sentiment-badge.negative { background: rgba(239, 68, 68, 0.1); color: var(--asus-danger); }
    
    /* Duration Badge */
    .duration-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        background: rgba(13, 110, 253, 0.1);
        color: var(--asus-primary);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    /* Filter Bar */
    .filter-bar {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 24px;
    }
    
    .filter-bar .form-control,
    .filter-bar .form-select {
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        padding: 10px 16px;
        transition: all 0.2s ease;
    }
    
    .filter-bar .form-control:focus,
    .filter-bar .form-select:focus {
        border-color: var(--asus-primary);
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }
    
    .filter-bar .btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
    }
    
    /* Live Chat Preview Panel */
    .chat-preview-panel {
        position: fixed;
        right: -450px;
        top: 0;
        width: 450px;
        height: 100vh;
        background: white;
        box-shadow: -4px 0 30px rgba(0, 0, 0, 0.15);
        transition: right 0.3s ease;
        z-index: 1050;
        display: flex;
        flex-direction: column;
    }
    
    .chat-preview-panel.active {
        right: 0;
    }
    
    .chat-preview-header {
        background: var(--asus-gradient);
        color: white;
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chat-preview-header h5 {
        margin: 0;
        font-weight: 700;
    }
    
    .chat-preview-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .chat-preview-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    .chat-preview-info {
        padding: 16px 24px;
        background: var(--asus-light);
        border-bottom: 1px solid #e2e8f0;
    }
    
    .chat-preview-info .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.85rem;
    }
    
    .chat-preview-info .info-label {
        color: #64748b;
    }
    
    .chat-preview-info .info-value {
        color: var(--asus-dark);
        font-weight: 600;
    }
    
    .chat-preview-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8fafc;
    }
    
    .chat-message {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
    }
    
    .chat-message.user {
        align-items: flex-end;
    }
    
    .chat-message.bot {
        align-items: flex-start;
    }
    
    .chat-bubble {
        max-width: 85%;
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 0.9rem;
        line-height: 1.5;
        position: relative;
    }
    
    .chat-message.user .chat-bubble {
        background: var(--asus-gradient);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .chat-message.bot .chat-bubble {
        background: white;
        color: var(--asus-dark);
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    
    .chat-time {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 4px;
        padding: 0 4px;
    }
    
    .chat-preview-footer {
        padding: 16px 24px;
        background: white;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 12px;
    }
    
    .chat-preview-footer .btn {
        flex: 1;
        padding: 12px;
        border-radius: 10px;
        font-weight: 600;
    }
    
    /* Activity Heatmap */
    .activity-heatmap {
        display: flex;
        gap: 4px;
        padding: 20px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    
    .heatmap-hour {
        flex: 1;
        height: 40px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .heatmap-hour:hover {
        transform: scaleY(1.2);
    }
    
    /* Overlay */
    .panel-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .panel-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Quick Stats Row */
    .quick-stat {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--asus-light);
        border-radius: 10px;
        font-size: 0.85rem;
    }
    
    .quick-stat i {
        font-size: 1.1rem;
    }
    
    /* Message Count Badge */
    .msg-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        background: var(--asus-light);
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--asus-dark);
    }
    
    /* Page Title */
    .page-title-box {
        background: var(--asus-gradient);
        color: white;
        padding: 30px;
        border-radius: 20px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .page-title-box::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .page-title-box h3 {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 8px;
    }
    
    .page-title-box p {
        opacity: 0.9;
        margin-bottom: 0;
    }
    
    /* Action Button */
    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e2e8f0;
        background: white;
        color: var(--asus-dark);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .action-btn:hover {
        background: var(--asus-primary);
        border-color: var(--asus-primary);
        color: white;
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

            <!-- Page Title -->
            <div class="page-title-box">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><i class="bi bi-chat-square-text me-2"></i>Conversation Logs</h3>
                        <p>Monitor and analyze chatbot interactions in real-time</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button class="btn btn-light" onclick="exportLogs()">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon me-3" style="background: var(--asus-gradient);">
                                <i class="bi bi-chat-dots"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($stats['total_conversations']); ?></div>
                                <div class="stats-label">Total Conversations</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon me-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($stats['today_count']); ?></div>
                                <div class="stats-label">Today's Chats</div>
                            </div>
                        </div>
                        <span class="stats-change positive position-absolute" style="top: 16px; right: 16px;">
                            <i class="bi bi-arrow-up"></i> Active
                        </span>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4 mb-md-0">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon me-3" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="bi bi-emoji-smile"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($stats['satisfaction_rate'], 0); ?>%</div>
                                <div class="stats-label">Satisfaction Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon me-3" style="background: linear-gradient(135deg, #6c63ff 0%, #5046e5 100%);">
                                <i class="bi bi-chat-left-text"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($stats['avg_messages_per_session'], 1); ?></div>
                                <div class="stats-label">Avg Messages/Chat</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Heatmap -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Today's Activity</h5>
                    <small class="text-muted">Conversations per hour</small>
                </div>
                <div class="card-body">
                    <div class="activity-heatmap">
                        <?php 
                        $maxHourly = max($hourly_data) ?: 1;
                        for ($h = 0; $h < 24; $h++): 
                            $intensity = $hourly_data[$h] / $maxHourly;
                            $hue = 220; // Blue base
                            $saturation = 80;
                            $lightness = 90 - ($intensity * 50);
                        ?>
                            <div class="heatmap-hour" 
                                 style="background: hsl(<?php echo $hue; ?>, <?php echo $saturation; ?>%, <?php echo $lightness; ?>%); color: <?php echo $intensity > 0.5 ? 'white' : '#1e293b'; ?>"
                                 title="<?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo $hourly_data[$h]; ?> chats">
                                <?php echo $hourly_data[$h] > 0 ? $hourly_data[$h] : ''; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="d-flex justify-content-between mt-2 text-muted" style="font-size: 0.7rem;">
                        <span>12 AM</span>
                        <span>6 AM</span>
                        <span>12 PM</span>
                        <span>6 PM</span>
                        <span>11 PM</span>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" 
                                   placeholder="Search by session, user, email..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="sentiment" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $sentiment_filter === 'all' ? 'selected' : ''; ?>>All Sentiment</option>
                            <option value="positive" <?php echo $sentiment_filter === 'positive' ? 'selected' : ''; ?>>😊 Positive</option>
                            <option value="neutral" <?php echo $sentiment_filter === 'neutral' ? 'selected' : ''; ?>>😐 Neutral</option>
                            <option value="negative" <?php echo $sentiment_filter === 'negative' ? 'selected' : ''; ?>>😞 Negative</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="time" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $time_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $time_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $time_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-2"></i>Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="admin_conversation_logs.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Conversations Table -->
            <div class="conversation-table">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Session</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Messages</th>
                                <th>Sentiment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($conversations) > 0): ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <?php
                                    $minutes = floor($conv['duration_seconds'] / 60);
                                    $seconds = $conv['duration_seconds'] % 60;
                                    $duration_display = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
                                    
                                    $avatar_colors = ['#0d6efd', '#6c63ff', '#10b981', '#f59e0b', '#ef4444'];
                                    $avatar_color = $avatar_colors[crc32($conv['user_name']) % count($avatar_colors)];
                                    $initials = strtoupper(substr($conv['user_name'], 0, 2));
                                    
                                    $sentiment_icons = [
                                        'positive' => '😊',
                                        'neutral' => '😐',
                                        'negative' => '😞'
                                    ];
                                    ?>
                                    <tr onclick="viewConversation(<?php echo $conv['conversation_id']; ?>)">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3" style="background: <?php echo $avatar_color; ?>;">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($conv['user_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($conv['customer_email'] ?: $conv['user_identifier']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code style="font-size: 0.75rem;"><?php echo htmlspecialchars(substr($conv['session_id'], 0, 12)); ?>...</code>
                                        </td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($conv['started_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($conv['started_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="duration-badge">
                                                <i class="bi bi-clock"></i>
                                                <?php echo $duration_display; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="msg-count"><?php echo $conv['message_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="sentiment-badge <?php echo $conv['sentiment'] ?: 'neutral'; ?>">
                                                <?php echo $sentiment_icons[$conv['sentiment']] ?? '😐'; ?>
                                                <?php echo ucfirst($conv['sentiment'] ?: 'Neutral'); ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <button class="action-btn me-1" onclick="viewConversation(<?php echo $conv['conversation_id']; ?>)" title="View">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn me-1" onclick="flagConversation(<?php echo $conv['conversation_id']; ?>)" title="Flag">
                                                <i class="bi bi-flag"></i>
                                            </button>
                                            <button class="action-btn" onclick="exportSingle(<?php echo $conv['conversation_id']; ?>)" title="Export">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 text-muted"></i>
                                        <p class="mt-3 mb-0 text-muted">No conversations found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Chat Preview Panel -->
    <div class="panel-overlay" onclick="closeChatPreview()"></div>
    <div class="chat-preview-panel" id="chatPreviewPanel">
        <div class="chat-preview-header">
            <h5><i class="bi bi-chat-dots me-2"></i>Conversation</h5>
            <button class="chat-preview-close" onclick="closeChatPreview()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="chat-preview-info" id="chatPreviewInfo">
            <!-- Info loaded via AJAX -->
        </div>
        <div class="chat-preview-body" id="chatPreviewBody">
            <!-- Messages loaded via AJAX -->
        </div>
        <div class="chat-preview-footer">
            <button class="btn btn-outline-warning" onclick="flagCurrentConversation()">
                <i class="bi bi-flag me-2"></i>Flag
            </button>
            <button class="btn btn-primary" onclick="exportCurrentConversation()">
                <i class="bi bi-download me-2"></i>Export
            </button>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>

    <script>
    let currentConversationId = null;
    
    // View Conversation in Side Panel
    function viewConversation(conversationId) {
        currentConversationId = conversationId;
        
        // Show panel
        document.getElementById('chatPreviewPanel').classList.add('active');
        document.querySelector('.panel-overlay').classList.add('active');
        
        // Show loading
        document.getElementById('chatPreviewBody').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 text-muted">Loading conversation...</p>
            </div>
        `;
        
        // Fetch conversation details
        fetch(`ajax/get_conversation_details.php?id=${conversationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayConversation(data.conversation, data.messages);
                } else {
                    document.getElementById('chatPreviewBody').innerHTML = `
                        <div class="alert alert-danger m-3">Error loading conversation</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('chatPreviewBody').innerHTML = `
                    <div class="alert alert-danger m-3">Failed to load conversation</div>
                `;
            });
    }
    
    function displayConversation(conversation, messages) {
        const sentimentColors = {
            'positive': '#10b981',
            'neutral': '#64748b',
            'negative': '#ef4444'
        };
        
        // Info section
        document.getElementById('chatPreviewInfo').innerHTML = `
            <div class="info-item">
                <span class="info-label">Session ID</span>
                <span class="info-value">${conversation.session_id.substring(0, 16)}...</span>
            </div>
            <div class="info-item">
                <span class="info-label">User</span>
                <span class="info-value">${conversation.user_name}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Duration</span>
                <span class="info-value">${conversation.duration_formatted || conversation.duration_seconds + 's'}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Sentiment</span>
                <span class="info-value" style="color: ${sentimentColors[conversation.sentiment] || '#64748b'}">
                    ${(conversation.sentiment || 'neutral').charAt(0).toUpperCase() + (conversation.sentiment || 'neutral').slice(1)}
                </span>
            </div>
        `;
        
        // Messages
        let messagesHtml = '';
        messages.forEach(msg => {
            const isUser = msg.message_type === 'user';
            messagesHtml += `
                <div class="chat-message ${isUser ? 'user' : 'bot'}">
                    <div class="chat-bubble">${escapeHtml(msg.message_content)}</div>
                    <div class="chat-time">${msg.time_only || msg.timestamp}</div>
                </div>
            `;
        });
        
        document.getElementById('chatPreviewBody').innerHTML = messagesHtml;
        
        // Scroll to bottom
        setTimeout(() => {
            const body = document.getElementById('chatPreviewBody');
            body.scrollTop = body.scrollHeight;
        }, 100);
    }
    
    function closeChatPreview() {
        document.getElementById('chatPreviewPanel').classList.remove('active');
        document.querySelector('.panel-overlay').classList.remove('active');
        currentConversationId = null;
    }
    
    function flagConversation(id) {
        const reason = prompt('Enter reason for flagging (optional):');
        if (reason !== null) {
            fetch('ajax/flag_conversation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({conversation_id: id, action: 'flag', reason: reason})
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? 'Conversation flagged!' : 'Error: ' + data.message);
            });
        }
    }
    
    function flagCurrentConversation() {
        if (currentConversationId) flagConversation(currentConversationId);
    }
    
    function exportLogs() {
        const urlParams = new URLSearchParams(window.location.search);
        window.location.href = `ajax/export_conversations.php?${urlParams.toString()}`;
    }
    
    function exportSingle(id) {
        window.location.href = `ajax/export_conversations.php?conversation_id=${id}`;
    }
    
    function exportCurrentConversation() {
        if (currentConversationId) exportSingle(currentConversationId);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Close panel on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeChatPreview();
    });
    </script>
</body>
</html>

</body>
</html>