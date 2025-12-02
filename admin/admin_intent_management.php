<?php
// ============================================
// Intent & Response Management - Chatbot Management
// Module D: Smart Laptop Advisor Admin
// ============================================

// Include database connection
require_once 'includes/db_connect.php';

// ============================================
// LOGIC SECTION - Data Fetching
// ============================================

// Fetch intent statistics
$stats_query = "SELECT 
    COUNT(*) as total_intents,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_intents,
    (SELECT COUNT(*) FROM training_phrases WHERE is_active = 1) as total_phrases,
    AVG(CASE WHEN usage_count > 0 THEN (success_count / usage_count) * 100 ELSE 0 END) as accuracy_rate
FROM intents";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Handle category filter
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($category_filter !== 'all') {
    $where_conditions[] = "i.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $where_conditions[] = "(i.intent_name LIKE ? OR i.display_name LIKE ? OR i.description LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch intents with training phrase count
$intents_query = "SELECT 
    i.intent_id,
    i.intent_name,
    i.display_name,
    i.description,
    i.category,
    i.is_active,
    i.usage_count,
    i.success_count,
    i.last_used_at,
    i.updated_at,
    COUNT(DISTINCT tp.phrase_id) as phrase_count,
    COUNT(DISTINCT ir.response_id) as response_count,
    CASE WHEN i.usage_count > 0 THEN (i.success_count / i.usage_count) * 100 ELSE 0 END as confidence
FROM intents i
LEFT JOIN training_phrases tp ON i.intent_id = tp.intent_id AND tp.is_active = 1
LEFT JOIN intent_responses ir ON i.intent_id = ir.intent_id AND ir.is_active = 1
{$where_clause}
GROUP BY i.intent_id
ORDER BY i.priority DESC, i.intent_name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($intents_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $intents_result = $stmt->get_result();
} else {
    $intents_result = $conn->query($intents_query);
}

$intents = [];
while ($row = $intents_result->fetch_assoc()) {
    $intents[] = $row;
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
    <title>Dynamic Command Management - Smart Laptop Advisor Admin</title>
    
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
                <h3>Dynamic Command Management</h3>
                <p class="text-subtitle text-muted">Define real-time AI instructions and responses - changes take effect immediately</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Chatbot</li>
                        <li class="breadcrumb-item active" aria-current="page">Dynamic Command Management</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Intent Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 col-sm-6 col-12">
            <div class="card">
                <div class="card-content">
                    <div class="card-body">
                        <div class="media d-flex">
                            <div class="align-self-center">
                                <i class="bi bi-lightbulb text-primary font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="primary"><?php echo number_format($stats['total_intents']); ?></h3>
                                <span>Total Intents</span>
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
                                <i class="bi bi-check-circle text-success font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="success"><?php echo number_format($stats['active_intents']); ?></h3>
                                <span>Active</span>
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
                                <i class="bi bi-chat-square-text text-info font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="info"><?php echo number_format($stats['total_phrases']); ?></h3>
                                <span>Training Phrases</span>
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
                                <i class="bi bi-graph-up text-warning font-large-2 float-left"></i>
                            </div>
                            <div class="media-body text-right">
                                <h3 class="warning"><?php echo number_format($stats['accuracy_rate'], 1); ?>%</h3>
                                <span>Accuracy Rate</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" name="search" placeholder="Search intents..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <select name="category" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="greeting" <?php echo $category_filter === 'greeting' ? 'selected' : ''; ?>>Greetings</option>
                    <option value="product_inquiry" <?php echo $category_filter === 'product_inquiry' ? 'selected' : ''; ?>>Product Inquiries</option>
                    <option value="recommendation" <?php echo $category_filter === 'recommendation' ? 'selected' : ''; ?>>Recommendations</option>
                    <option value="support" <?php echo $category_filter === 'support' ? 'selected' : ''; ?>>Support</option>
                </select>
            </form>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#testChatbotModal">
                    <i class="bi bi-chat-square-dots me-2"></i>Test Chatbot
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIntentModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Intent
                </button>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Intents Grid -->
    <section class="section">
        <div class="row" id="intentsGrid">
            <?php if (count($intents) > 0): ?>
                <?php foreach ($intents as $intent): ?>
                    <?php
                    $card_class = $intent['is_active'] ? 'active' : 'inactive';
                    $status_badge = $intent['is_active'] ? 'success' : 'danger';
                    $status_text = $intent['is_active'] ? 'Active' : 'Inactive';
                    
                    // Icon mapping
                    $icons = [
                        'greeting' => 'hand-wave',
                        'product_inquiry' => 'laptop',
                        'recommendation' => 'controller',
                        'support' => 'currency-dollar'
                    ];
                    $icon = $icons[$intent['category']] ?? 'lightbulb';
                    
                    // Background color mapping
                    $bg_colors = [
                        'greeting' => 'primary',
                        'product_inquiry' => 'success',
                        'recommendation' => 'warning',
                        'support' => 'info'
                    ];
                    $bg_color = $bg_colors[$intent['category']] ?? 'secondary';
                    
                    // Fetch sample phrases
                    $phrases_query = "SELECT phrase_text FROM training_phrases 
                                     WHERE intent_id = ? AND is_active = 1 LIMIT 6";
                    $phrases_stmt = $conn->prepare($phrases_query);
                    $phrases_stmt->bind_param('i', $intent['intent_id']);
                    $phrases_stmt->execute();
                    $phrases_result = $phrases_stmt->get_result();
                    $sample_phrases = [];
                    while ($phrase = $phrases_result->fetch_assoc()) {
                        $sample_phrases[] = $phrase['phrase_text'];
                    }
                    
                    // Fetch default response
                    $response_query = "SELECT response_text FROM intent_responses 
                                      WHERE intent_id = ? AND is_default = 1 LIMIT 1";
                    $response_stmt = $conn->prepare($response_query);
                    $response_stmt->bind_param('i', $intent['intent_id']);
                    $response_stmt->execute();
                    $response_result = $response_stmt->get_result();
                    $default_response = $response_result->fetch_assoc();
                    ?>
                    
                    <div class="col-xl-6 col-lg-6 col-md-12 mb-4">
                        <div class="card intent-card <?php echo $card_class; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-<?php echo $bg_color; ?> me-3">
                                            <span class="avatar-content">
                                                <i class="bi bi-<?php echo $icon; ?> text-white"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($intent['intent_name']); ?></h5>
                                            <small class="text-muted"><?php echo htmlspecialchars($intent['display_name']); ?></small>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editIntent(<?php echo $intent['intent_id']; ?>)">
                                                <i class="bi bi-pencil me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="testIntent(<?php echo $intent['intent_id']; ?>)">
                                                <i class="bi bi-play-circle me-2"></i>Test</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="duplicateIntent(<?php echo $intent['intent_id']; ?>)">
                                                <i class="bi bi-files me-2"></i>Duplicate</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteIntent(<?php echo $intent['intent_id']; ?>)">
                                                <i class="bi bi-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 class="mb-2">Training Phrases (<?php echo $intent['phrase_count']; ?>):</h6>
                                    <div class="mb-2">
                                        <?php foreach ($sample_phrases as $phrase): ?>
                                            <span class="example-phrase"><?php echo htmlspecialchars($phrase); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <?php if ($default_response): ?>
                                <div class="mb-3">
                                    <h6 class="mb-2">Response Preview:</h6>
                                    <div class="response-preview">
                                        <?php echo htmlspecialchars(substr($default_response['response_text'], 0, 150)); ?>
                                        <?php echo strlen($default_response['response_text']) > 150 ? '...' : ''; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <small class="text-muted">Confidence</small>
                                        <div class="confidence-bar mt-1">
                                            <div class="confidence-fill bg-success" style="width: <?php echo $intent['confidence']; ?>%"></div>
                                        </div>
                                        <small class="text-success"><?php echo number_format($intent['confidence'], 0); ?>%</small>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Usage Count</small>
                                        <h6 class="mb-0 text-primary"><?php echo number_format($intent['usage_count']); ?></h6>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Last Updated</small>
                                        <h6 class="mb-0"><?php echo $intent['updated_at'] ? date('M d', strtotime($intent['updated_at'])) : 'N/A'; ?></h6>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                    <span class="badge bg-<?php echo $bg_color; ?>"><?php echo ucwords(str_replace('_', ' ', $intent['category'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No intents found matching your criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Add New Intent Modal -->
<div class="modal fade" id="addIntentModal" tabindex="-1" aria-labelledby="addIntentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIntentModalLabel">Add New Intent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addIntentForm" method="POST" action="api/intent_actions_api.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="intent_name" class="form-label">Intent Name *</label>
                        <input type="text" class="form-control" id="intent_name" name="intent_name" required 
                               placeholder="e.g., find_laptop, warranty_info">
                        <small class="text-muted">Use lowercase with underscores</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="display_name" class="form-label">Display Name *</label>
                        <input type="text" class="form-control" id="display_name" name="display_name" required
                               placeholder="e.g., Find Laptop, Warranty Information">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  placeholder="Brief description of what this intent handles"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select category</option>
                                <option value="greeting">Greeting</option>
                                <option value="product_inquiry">Product Inquiry</option>
                                <option value="recommendation">Recommendation</option>
                                <option value="support">Support</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <input type="number" class="form-control" id="priority" name="priority" value="5" min="1" max="10">
                            <small class="text-muted">1 (low) - 10 (high)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="training_phrases" class="form-label">Training Phrases</label>
                        <textarea class="form-control" id="training_phrases" name="training_phrases" rows="4"
                                  placeholder="Enter one phrase per line, e.g.:&#10;Looking for a computer&#10;Find me a laptop&#10;I want to buy a laptop"></textarea>
                        <small class="text-muted">Enter one phrase per line</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_response" class="form-label">Default Response *</label>
                        <textarea class="form-control" id="default_response" name="default_response" rows="4" required
                                  placeholder="The AI will be instructed to include this information when this intent is triggered"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">
                            Active (intent will be used immediately)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Intent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Intent Modal -->
<div class="modal fade" id="editIntentModal" tabindex="-1" aria-labelledby="editIntentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editIntentModalLabel">Edit Intent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editIntentForm" method="POST" action="api/intent_actions_api.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="intent_id" id="edit_intent_id">
                    
                    <div class="mb-3">
                        <label for="edit_intent_name" class="form-label">Intent Name *</label>
                        <input type="text" class="form-control" id="edit_intent_name" name="intent_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_display_name" class="form-label">Display Name *</label>
                        <input type="text" class="form-control" id="edit_display_name" name="display_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_category" class="form-label">Category *</label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <option value="greeting">Greeting</option>
                                <option value="product_inquiry">Product Inquiry</option>
                                <option value="recommendation">Recommendation</option>
                                <option value="support">Support</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_priority" class="form-label">Priority</label>
                            <input type="number" class="form-control" id="edit_priority" name="priority" min="1" max="10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_training_phrases" class="form-label">Training Phrases</label>
                        <textarea class="form-control" id="edit_training_phrases" name="training_phrases" rows="4"></textarea>
                        <small class="text-muted">Enter one phrase per line</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_default_response" class="form-label">Default Response *</label>
                        <textarea class="form-control" id="edit_default_response" name="default_response" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Intent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Chatbot Modal -->
<div class="modal fade" id="testChatbotModal" tabindex="-1" aria-labelledby="testChatbotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testChatbotModalLabel">Test Chatbot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="testChatBox" style="height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; background: #f8f9fa; margin-bottom: 15px;">
                    <div class="text-center text-muted">
                        <p>Start testing your chatbot intents here</p>
                    </div>
                </div>
                <form id="testChatForm">
                    <div class="input-group">
                        <input type="text" class="form-control" id="testChatInput" placeholder="Type a test message...">
                        <button class="btn btn-primary" type="submit">Send</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="clearTestChat()">Clear Chat</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.intent-card {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}
.intent-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.intent-card.active {
    border-left-color: #28a745;
    background: rgba(40, 167, 69, 0.05);
}
.intent-card.inactive {
    border-left-color: #dc3545;
    background: rgba(220, 53, 69, 0.05);
}
.example-phrase {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85rem;
    margin: 2px;
    display: inline-block;
}
.response-preview {
    background: #e3f2fd;
    border-left: 3px solid #2196f3;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}
.confidence-bar {
    height: 6px;
    border-radius: 3px;
    background: #e9ecef;
    overflow: hidden;
}
.confidence-fill {
    height: 100%;
    transition: width 0.3s ease;
}

</style>
<script>
// JavaScript Functions for Intent Management

// Edit Intent
function editIntent(intentId) {
    // Fetch intent data
    fetch(`api/intent_actions_api.php?action=get&intent_id=${intentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const intent = data.intent;
                
                // Populate form
                document.getElementById('edit_intent_id').value = intent.intent_id;
                document.getElementById('edit_intent_name').value = intent.intent_name;
                document.getElementById('edit_display_name').value = intent.display_name;
                document.getElementById('edit_description').value = intent.description || '';
                document.getElementById('edit_category').value = intent.category;
                document.getElementById('edit_priority').value = intent.priority;
                document.getElementById('edit_is_active').checked = intent.is_active == 1;
                
                // Load training phrases
                document.getElementById('edit_training_phrases').value = data.training_phrases.join('\n');
                
                // Load default response
                document.getElementById('edit_default_response').value = data.default_response || '';
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editIntentModal'));
                modal.show();
            } else {
                alert('Error loading intent: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load intent data');
        });
}

// Test Intent
function testIntent(intentId) {
    // Fetch intent training phrases
    fetch(`api/intent_actions_api.php?action=get&intent_id=${intentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const phrases = data.training_phrases;
                if (phrases.length > 0) {
                    const testPhrase = phrases[0]; // Use first training phrase
                    document.getElementById('testChatInput').value = testPhrase;
                    const modal = new bootstrap.Modal(document.getElementById('testChatbotModal'));
                    modal.show();
                } else {
                    alert('No training phrases found for this intent');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to test intent');
        });
}

// Duplicate Intent
function duplicateIntent(intentId) {
    if (confirm('Create a copy of this intent?')) {
        fetch('api/intent_actions_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=duplicate&intent_id=${intentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Intent duplicated successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to duplicate intent');
        });
    }
}

// Delete Intent
function deleteIntent(intentId) {
    if (confirm('Are you sure you want to delete this intent? This action cannot be undone.')) {
        fetch('api/intent_actions_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete&intent_id=${intentId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Intent deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete intent');
        });
    }
}

// Test Chatbot Functions
let testSessionId = null;

document.getElementById('testChatForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('testChatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Display user message
    addTestMessage(message, 'user');
    input.value = '';
    
    // Initialize session if needed
    if (!testSessionId) {
        const sessionResponse = await fetch('../api/chat_start.php', {method: 'POST'});
        const sessionData = await sessionResponse.json();
        if (sessionData.success) {
            testSessionId = sessionData.session_id;
        }
    }
    
    // Send message
    try {
        const response = await fetch('../api/chat_message.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                session_id: testSessionId,
                message: message
            })
        });
        
        const data = await response.json();
        if (data.success) {
            addTestMessage(data.response, 'bot');
        } else {
            addTestMessage('Error: ' + data.error, 'system');
        }
    } catch (error) {
        console.error('Error:', error);
        addTestMessage('Failed to send message', 'system');
    }
});

function addTestMessage(message, sender) {
    const chatBox = document.getElementById('testChatBox');
    const messageDiv = document.createElement('div');
    messageDiv.className = `mb-2 ${sender === 'user' ? 'text-end' : ''}`;
    
    const bubble = document.createElement('div');
    bubble.className = `d-inline-block p-2 rounded ${
        sender === 'user' ? 'bg-primary text-white' : 
        sender === 'bot' ? 'bg-white border' : 
        'bg-warning'
    }`;
    bubble.style.maxWidth = '80%';
    bubble.textContent = message;
    
    messageDiv.appendChild(bubble);
    chatBox.appendChild(messageDiv);
    chatBox.scrollTop = chatBox.scrollHeight;
}

function clearTestChat() {
    document.getElementById('testChatBox').innerHTML = '<div class="text-center text-muted"><p>Chat cleared</p></div>';
    testSessionId = null;
}

// Form submissions
document.getElementById('addIntentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('api/intent_actions_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Intent created successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create intent');
    });
});

document.getElementById('editIntentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('api/intent_actions_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Intent updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update intent');
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>
</body>
</html>
