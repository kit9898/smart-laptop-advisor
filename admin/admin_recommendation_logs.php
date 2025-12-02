<?php
// admin_recommendation_logs.php - Recommendation Feedback Logs
// Module C: AI Recommendation Engine

// Include database connection
require_once 'includes/db_connect.php';

// ===================== LOGIC SECTION =====================

// Get filter parameters
$persona_filter = isset($_GET['persona']) ? intval($_GET['persona']) : null;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : null; // 1 for Like, -1 for Dislike
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Fetch statistics
$stats_query = "SELECT 
    COUNT(*) as total_feedback,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as total_likes,
    SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) as total_dislikes
    FROM recommendation_ratings
    WHERE created_at BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $stats_query);
$date_to_end = $date_to . ' 23:59:59';
mysqli_stmt_bind_param($stmt, 'ss', $date_from, $date_to_end);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stmt);

$satisfaction_score = $stats['total_feedback'] > 0 ? ($stats['total_likes'] / $stats['total_feedback']) * 100 : 0;

// Build WHERE clause for logs query
$where_conditions = ["rr.created_at BETWEEN ? AND ?"];
$bind_types = "ss";
$bind_params = [$date_from, $date_to_end];

if ($persona_filter) {
    $where_conditions[] = "per.persona_id = ?";
    $bind_types .= "i";
    $bind_params[] = $persona_filter;
}

if ($rating_filter) {
    $where_conditions[] = "rr.rating = ?";
    $bind_types .= "i";
    $bind_params[] = $rating_filter;
}

if ($search_term) {
    $where_conditions[] = "(u.full_name LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)";
    $bind_types .= "sss";
    $search_param = "%$search_term%";
    $bind_params[] = $search_param;
    $bind_params[] = $search_param;
    $bind_params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch recommendation ratings logs
$logs_query = "SELECT 
    rr.*,
    u.full_name,
    u.email,
    u.primary_use_case,
    p.product_name,
    p.brand,
    p.image_url,
    per.name as persona_name,
    per.color_theme
    FROM recommendation_ratings rr
    LEFT JOIN users u ON rr.user_id = u.user_id
    LEFT JOIN products p ON rr.product_id = p.product_id
    LEFT JOIN personas per ON u.primary_use_case = per.name
    WHERE $where_clause
    ORDER BY rr.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $logs_query);
$bind_types .= "ii";
$bind_params[] = $per_page;
$bind_params[] = $offset;
mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($stmt);
$logs_result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Count total logs for pagination
$count_query = "SELECT COUNT(*) as total 
    FROM recommendation_ratings rr
    LEFT JOIN users u ON rr.user_id = u.user_id
    LEFT JOIN products p ON rr.product_id = p.product_id
    LEFT JOIN personas per ON u.primary_use_case = per.name
    WHERE $where_clause";
$stmt = mysqli_prepare($conn, $count_query);
// Remove last two parameters (LIMIT and OFFSET) for count
$count_bind_types = substr($bind_types, 0, -2);
$count_bind_params = array_slice($bind_params, 0, -2);
mysqli_stmt_bind_param($stmt, $count_bind_types, ...$count_bind_params);
mysqli_stmt_execute($stmt);
$count_result = mysqli_stmt_get_result($stmt);
$total_logs = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($stmt);

$total_pages = ceil($total_logs / $per_page);

// Fetch all personas for filter dropdown
$personas_query = "SELECT persona_id, name FROM personas ORDER BY name ASC";
$personas_result = mysqli_query($conn, $personas_query);

// ===================== VIEW SECTION =====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendation Feedback - Smart Laptop Advisor Admin</title>
    
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
                <h3>Recommendation Feedback</h3>
                <p class="text-subtitle text-muted">View user feedback and satisfaction logs</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">AI Engine</li>
                        <li class="breadcrumb-item active" aria-current="page">Feedback Logs</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon purple">
                                <i class="bi bi-chat-text"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Feedback</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($stats['total_feedback']); ?></h6>
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
                                <i class="bi bi-emoji-smile"></i>
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
                                <i class="bi bi-hand-thumbs-up"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Likes</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($stats['total_likes']); ?></h6>
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
                                <i class="bi bi-hand-thumbs-down"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Dislikes</h6>
                            <h6 class="font-extrabold mb-0"><?php echo number_format($stats['total_dislikes']); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filter Feedback Logs</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="admin_recommendation_logs.php">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="persona">Persona</label>
                                    <select class="form-select" id="persona" name="persona">
                                        <option value="">All Personas</option>
                                        <?php 
                                        if ($personas_result && mysqli_num_rows($personas_result) > 0):
                                            while ($persona = mysqli_fetch_assoc($personas_result)):
                                                $selected = ($persona['persona_id'] == $persona_filter) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $persona['persona_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($persona['name']); ?>
                                        </option>
                                        <?php 
                                            endwhile;
                                        endif;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="rating">Rating</label>
                                    <select class="form-select" id="rating" name="rating">
                                        <option value="">All Ratings</option>
                                        <option value="1" <?php echo ($rating_filter == 1) ? 'selected' : ''; ?>>Like (Thumbs Up)</option>
                                        <option value="-1" <?php echo ($rating_filter == -1) ? 'selected' : ''; ?>>Dislike (Thumbs Down)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from">From Date</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to">To Date</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="search">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search by User Name, Product Model, or Brand..." 
                                           value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="btn-group w-100">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Filter
                                        </button>
                                        <a href="admin_recommendation_logs.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- Recommendation Logs Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Feedback History</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="recommendationLogsTable">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Persona</th>
                                    <th>Product</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($logs_result && mysqli_num_rows($logs_result) > 0):
                                    while ($log = mysqli_fetch_assoc($logs_result)):
                                        $rating_badge = $log['rating'] == 1 
                                            ? '<span class="badge bg-success"><i class="bi bi-hand-thumbs-up-fill me-1"></i> Like</span>' 
                                            : '<span class="badge bg-danger"><i class="bi bi-hand-thumbs-down-fill me-1"></i> Dislike</span>';
                                ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($log['persona_name']): ?>
                                        <span class="badge bg-light-<?php echo $log['color_theme']; ?>">
                                            <?php echo htmlspecialchars($log['persona_name']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-light-secondary"><?php echo htmlspecialchars($log['primary_use_case']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['product_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['brand']); ?></small>
                                    </td>
                                    <td><?php echo $rating_badge; ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>No feedback logs found for the selected filters.
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&persona=<?php echo $persona_filter; ?>&rating=<?php echo $rating_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search_term); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/admin_footer.php';
?>
        </div>
    </div>
</body>
</html>
