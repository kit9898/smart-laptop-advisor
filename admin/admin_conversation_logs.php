<?php
// ============================================
// Conversation Logs - Chatbot Management
// Module D: Smart Laptop Advisor Admin
// ============================================

// Include database connection
require_once 'includes/db_connect.php';

// ============================================
// LOGIC SECTION - Data Fetching
// ============================================

// Fetch conversation statistics
$stats_query = "SELECT 
    COUNT(*) as total_conversations,
    COUNT(CASE WHEN DATE(started_at) = CURDATE() THEN 1 END) as today_count,
    AVG(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) * 100 as satisfaction_rate,
    AVG(message_count / 2) as avg_messages_per_session
FROM conversations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Handle filters
$sentiment_filter = isset($_GET['sentiment']) ? $_GET['sentiment'] : 'all';
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause for conversations
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
    $where_conditions[] = "(c.session_id LIKE ? OR u.full_name LIKE ? OR c.user_ip LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
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

// ============================================
// VIEW SECTION - HTML Output
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation Logs - Smart Laptop Advisor Admin</title>
    
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
                <h3>Conversation Logs</h3>
                <p class="text-subtitle text-muted">Monitor and analyze chatbot interactions</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Chatbot</li>
                        <li class="breadcrumb-item active" aria-current="page">Conversations</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Conversation Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 col-sm-6 col-12">
            <div class="card">
                <div class="card-content">
                    <div class="card-body">
                        <div class="media d-flex">
                            <div class="align-self-center">
                                <i class="bi bi-chat-dots text-primary font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="primary"><?php echo number_format($stats['total_conversations']); ?></h3>
                                <span>Total Conversations</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-6 col-12">
            <div class="card">
                <div class="card-content">
                    <div class="card-body">
                        <div class="media d-flex">
                            <div class="align-self-center">
                                <i class="bi bi-clock text-warning font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="warning"><?php echo number_format($stats['today_count']); ?></h3>
                                <span>Today</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-6 col-12">
            <div class="card">
                <div class="card-content">
                    <div class="card-body">
                        <div class="media d-flex">
                            <div class="align-self-center">
                                <i class="bi bi-emoji-smile text-success font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="success"><?php echo number_format($stats['satisfaction_rate'], 1); ?>%</h3>
                                <span>Satisfaction Rate</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-6 col-12">
            <div class="card">
                <div class="card-content">
                    <div class="card-body">
                        <div class="media d-flex">
                            <div class="align-self-center">
                                <i class="bi bi-arrow-repeat text-info font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="info"><?php echo number_format($stats['avg_messages_per_session'], 1); ?></h3>
                                <span>Avg Messages/Session</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" name="search" placeholder="Search conversations..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <select name="sentiment" class="form-select" style="max-width: 150px;" onchange="this.form.submit()">
                    <option value="all" <?php echo $sentiment_filter === 'all' ? 'selected' : ''; ?>>All Sentiments</option>
                    <option value="positive" <?php echo $sentiment_filter === 'positive' ? 'selected' : ''; ?>>Positive</option>
                    <option value="neutral" <?php echo $sentiment_filter === 'neutral' ? 'selected' : ''; ?>>Neutral</option>
                    <option value="negative" <?php echo $sentiment_filter === 'negative' ? 'selected' : ''; ?>>Negative</option>
                </select>
                <select name="time" class="form-select" style="max-width: 150px;" onchange="this.form.submit()">
                    <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $time_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $time_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $time_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </form>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                <button class="btn btn-outline-primary" onclick="exportLogs()">
                    <i class="bi bi-download me-2"></i>Export Logs
                </button>
                <button class="btn btn-outline-info" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Conversations Table -->
    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Conversations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="conversationsTable">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>User</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Messages</th>
                                <th>Sentiment</th>
                                <th>Outcome</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($conversations) > 0): ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <?php
                                    // Format duration
                                    $minutes = floor($conv['duration_seconds'] / 60);
                                    $seconds = $conv['duration_seconds'] % 60;
                                    $duration_display = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
                                    
                                    // Sentiment badge
                                    $sentiment_class = $conv['sentiment'] === 'positive' ? 'success' : 
                                                      ($conv['sentiment'] === 'negative' ? 'danger' : 'secondary');
                                    $sentiment_icon = $conv['sentiment'] === 'positive' ? 'emoji-smile' : 
                                                     ($conv['sentiment'] === 'negative' ? 'emoji-frown' : 'dash-circle');
                                    
                                    // Outcome badge
                                    $outcome_class = strpos($conv['outcome'], 'order') !== false || 
                                                    strpos($conv['outcome'], 'recommendation') !== false ? 'success' : 
                                                    (strpos($conv['outcome'], 'abandoned') !== false ? 'danger' : 'primary');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($conv['session_id']); ?></strong><br>
                                            <small class="text-muted"><?php echo ucfirst($conv['source']); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    <div class="avatar-content bg-primary text-white">
                                                        <i class="bi bi-person"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p class="mb-0"><?php echo htmlspecialchars($conv['user_name']); ?></p>
                                                    <small class="text-muted"><?php echo htmlspecialchars($conv['user_identifier']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="mb-0"><?php echo date('Y-m-d', strtotime($conv['started_at'])); ?></p>
                                                <small class="text-muted"><?php echo date('H:i:s', strtotime($conv['started_at'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $duration_display; ?></span>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <h6 class="mb-0"><?php echo $conv['message_count']; ?></h6>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $sentiment_class; ?>">
                                                <i class="bi bi-<?php echo $sentiment_icon; ?> me-1"></i><?php echo ucfirst($conv['sentiment']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $outcome_class; ?>"><?php echo ucwords(str_replace('_', ' ', $conv['outcome'])); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewConversation(<?php echo $conv['conversation_id']; ?>)">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No conversations found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Conversation Details Modal -->
<div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conversationModalLabel">Conversation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="conversationContent">
                <!-- Conversation content will be loaded here via AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-warning" onclick="flagConversation()">
                    <i class="bi bi-flag me-2"></i>Flag for Review
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="exportConversation()">
                    <i class="bi bi-download me-2"></i>Export
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>

        </div>
    </div>

<script>
// Export Logs Function
function exportLogs() {
    // Get current filter values
    const urlParams = new URLSearchParams(window.location.search);
    const sentiment = urlParams.get('sentiment') || 'all';
    const time = urlParams.get('time') || 'all';
    const search = urlParams.get('search') || '';
    
    // Build export URL with filters
    const exportUrl = `ajax/export_conversations.php?sentiment=${sentiment}&time=${time}&search=${encodeURIComponent(search)}`;
    
    // Trigger download
    window.location.href = exportUrl;
}

// Export Single Conversation
function exportConversation() {
    // Get the conversation ID from the modal (you'll need to store it when opening the modal)
    const conversationId = document.getElementById('conversationModal').dataset.conversationId;
    
    if (conversationId) {
        window.location.href = `ajax/export_conversations.php?conversation_id=${conversationId}`;
    }
}

// Flag Conversation Function
function flagConversation() {
    const conversationId = document.getElementById('conversationModal').dataset.conversationId;
    
    if (!conversationId) {
        alert('No conversation selected');
        return;
    }
    
    const reason = prompt('Enter reason for flagging this conversation (optional):');
    
    if (reason !== null) { // User didn't cancel
        fetch('ajax/flag_conversation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                conversation_id: conversationId,
                action: 'flag',
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Conversation flagged successfully');
            } else {
                alert('Error flagging conversation: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error flagging conversation');
        });
    }
}

// Updated viewConversation function
function viewConversation(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('conversationModal'));
    const contentDiv = document.getElementById('conversationContent');
    const modalElement = document.getElementById('conversationModal');
    
    // Store conversation ID for export/flag functions
    modalElement.dataset.conversationId = conversationId;
    
    // Show loading
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch conversation details via AJAX
    fetch(`ajax/get_conversation_details.php?id=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayConversationDetails(data.conversation, data.messages);
            } else {
                contentDiv.innerHTML = 
                    '<div class="alert alert-danger">Error loading conversation details</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = 
                '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        });
}

// Updated displayConversationDetails function
function displayConversationDetails(conversation, messages) {
    const sentimentClass = conversation.sentiment === 'positive' ? 'success' : 
                          (conversation.sentiment === 'negative' ? 'danger' : 'secondary');
    const outcomeClass = conversation.outcome && conversation.outcome.includes('order') ? 'success' : 'primary';
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h6><i class="bi bi-info-circle me-2"></i>Session Information</h6>
                <p><strong>Session ID:</strong> ${conversation.session_id}</p>
                <p><strong>User:</strong> ${conversation.user_name}</p>
                <p><strong>Start Time:</strong> ${conversation.started_formatted || conversation.started_at}</p>
                <p><strong>Duration:</strong> ${conversation.duration_formatted || conversation.duration_seconds + 's'}</p>
            </div>
            <div class="col-md-6">
                <h6><i class="bi bi-bar-chart me-2"></i>Analysis</h6>
                <p><strong>Sentiment:</strong> <span class="badge bg-${sentimentClass}">${conversation.sentiment || 'neutral'}</span></p>
                <p><strong>Outcome:</strong> <span class="badge bg-${outcomeClass}">${conversation.outcome ? conversation.outcome.replace(/_/g, ' ') : 'in progress'}</span></p>
                <p><strong>Messages:</strong> ${conversation.message_count}</p>
            </div>
        </div>
        
        <h6><i class="bi bi-chat-dots me-2"></i>Full Conversation</h6>
        <div class="chatbox-container" style="height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; background-color: #ffffff !important;">
    `;
    
    messages.forEach((msg) => {
        // Fix: Use message_type instead of sender_type
        const isUser = msg.message_type === 'user';
        
        if (isUser) {
            // User message (right side, blue background)
            html += `
                <div style="margin-bottom: 15px; display: flex; justify-content: flex-end;">
                    <div style="max-width: 70%;">
                        <div style="padding: 10px 16px; border-radius: 18px; background-color: #007bff !important; color: #ffffff !important; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            <div style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.4;">${msg.message_content}</div>
                        </div>
                        <div style="font-size: 0.7rem; color: #6c757d; margin-top: 4px; padding: 0 4px; text-align: right;">
                            ${msg.timestamp || msg.time_only}
                            ${msg.response_time_display ? ' • ' + msg.response_time_display : ''}
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Bot message (left side, light grey background)
            html += `
                <div style="margin-bottom: 15px; display: flex; justify-content: flex-start;">
                    <div style="max-width: 70%;">
                        <div style="padding: 10px 16px; border-radius: 18px; background-color: #f1f3f5 !important; color: #212529 !important; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                            <div style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.4;">${msg.message_content}</div>
                        </div>
                        <div style="font-size: 0.7rem; color: #6c757d; margin-top: 4px; padding: 0 4px; text-align: left;">
                            ${msg.timestamp || msg.time_only}
                            ${msg.intent_detected ? ' • Intent: ' + msg.intent_detected : ''}
                            ${msg.response_time_display ? ' • ' + msg.response_time_display : ''}
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    html += '</div>';
    
    document.getElementById('conversationContent').innerHTML = html;
    
    // Auto-scroll to bottom
    setTimeout(() => {
        const chatbox = document.querySelector('.chatbox-container');
        if (chatbox) {
            chatbox.scrollTop = chatbox.scrollHeight;
        }
    }, 100);
}
</script>

</body>
</html>