<?php
/**
 * Admin User Management Page
 * Module E: User & System Administration
 * Manage administrator accounts and permissions
 */

// Start session and include necessary files
session_start();
require_once 'includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// ==================== DATA FETCHING ====================

// Get filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch admin statistics
$stats = [
    'total_admins' => 0,
    'active_admins' => 0,
    'online_now' => 0,
    'total_logins' => 0
];

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) as online,
    SUM(login_count) as total_logins
FROM admin_users";

$result = mysqli_query($conn, $stats_query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_admins'] = $row['total'];
    $stats['active_admins'] = $row['active'];
    $stats['online_now'] = $row['online'];
    $stats['total_logins'] = $row['total_logins'] ?? 0;
}

// Fetch all roles for filter dropdown
$roles_query = "SELECT role_id, role_name FROM roles WHERE status = 'active' ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_query);
$roles = [];
while ($role = mysqli_fetch_assoc($roles_result)) {
    $roles[] = $role;
}

// Build admin query with filters
$admin_query = "SELECT 
    a.admin_id,
    a.admin_code,
    CONCAT(a.first_name, ' ', a.last_name) as full_name,
    a.email,
    a.phone,
    a.status,
    a.last_login,
    a.login_count,
    a.created_at,
    a.profile_picture,
    a.two_factor_enabled,
    r.role_name,
    r.role_code
FROM admin_users a
LEFT JOIN roles r ON a.role_id = r.role_id
WHERE 1=1";

$params = [];
$types = '';

if (!empty($search_term)) {
    $admin_query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR a.admin_code LIKE ?)";
    $search_param = "%{$search_term}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($role_filter)) {
    $admin_query .= " AND a.role_id = ?";
    $params[] = $role_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $admin_query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$admin_query .= " ORDER BY a.created_at DESC";

$stmt = mysqli_prepare($conn, $admin_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$admins_result = mysqli_stmt_get_result($stmt);

// Fetch admins into array
$admins = [];
while ($row = mysqli_fetch_assoc($admins_result)) {
    $admins[] = $row;
}

// ==================== VIEW LAYER ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Management - Smart Laptop Advisor</title>
    
    <!-- CSS Files -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="source/assets/css/bootstrap.css">
    <link rel="stylesheet" href="source/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="source/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="source/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="source/assets/css/app.css">
    <link rel="shortcut icon" href="source/assets/images/favicon.svg" type="image/x-icon">
    
    <style>
        .admin-avatar {
            width: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-suspended {
            background-color: #fff3cd;
            color: #856404;
        }
        .online-indicator {
            width: 10px;
            height: 10px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Administrator Management</h3>
                            <p class="text-subtitle text-muted">Manage admin accounts and permissions</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Administrators</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Admin Statistics -->
                <div class="row">
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon purple">
                                            <i class="iconly-boldProfile"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Total Admins</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['total_admins']; ?></h6>
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
                                        <h6 class="text-muted font-semibold">Active</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['active_admins']; ?></h6>
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
                                            <i class="iconly-boldShow"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Online Now</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['online_now']; ?></h6>
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
                                            <i class="iconly-boldActivity"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Total Logins</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo number_format($stats['total_logins']); ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Management Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title">Administrator Accounts</h4>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary me-2" onclick="exportAdmins()">
                                            <i class="bi bi-download"></i> Export
                                        </button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                            <i class="bi bi-plus-circle"></i> Add Admin
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Search and Filter -->
                                <div class="filter-section">
                                    <form method="GET" action="" id="filterForm">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Search</label>
                                                <input type="text" class="form-control" name="search" 
                                                       placeholder="Search admins..." 
                                                       value="<?php echo htmlspecialchars($search_term); ?>">
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role">
                                                    <option value="">All Roles</option>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?php echo $role['role_id']; ?>" 
                                                                <?php echo $role_filter == $role['role_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="">All Status</option>
                                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-search"></i> Filter
                                                    </button>
                                                    <a href="admin_admins.php" class="btn btn-outline-secondary">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Admins Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Admin</th>
                                                <th>Contact</th>
                                                <th>Role</th>
                                                <th>Last Login</th>
                                                <th>Total Logins</th>
                                                <th>2FA</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($admins) > 0): ?>
                                                <?php foreach ($admins as $admin): ?>
                                                    <?php
                                                    $is_online = false;
                                                    if ($admin['last_login']) {
                                                        $last_login_time = strtotime($admin['last_login']);
                                                        $current_time = time();
                                                        $is_online = ($current_time - $last_login_time) < 900; // 15 minutes
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($admin['profile_picture'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($admin['profile_picture']); ?>" 
                                                                         alt="Avatar" class="admin-avatar me-2">
                                                                <?php else: ?>
                                                                    <div class="avatar avatar-md bg-primary me-2">
                                                                        <span class="avatar-content">
                                                                            <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                                                    <?php if ($is_online): ?>
                                                                        <span class="online-indicator" title="Online"></span>
                                                                    <?php endif; ?>
                                                                    <br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($admin['admin_code']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($admin['email']); ?>
                                                            <?php if (!empty($admin['phone'])): ?>
                                                                <br><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($admin['phone']); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light-info">
                                                                <?php echo htmlspecialchars($admin['role_name']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($admin['last_login']) {
                                                                echo date('M d, Y H:i', strtotime($admin['last_login']));
                                                            } else {
                                                                echo '<span class="text-muted">Never</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light-secondary">
                                                                <?php echo number_format($admin['login_count']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($admin['two_factor_enabled']): ?>
                                                                <i class="bi bi-shield-check text-success" title="2FA Enabled"></i>
                                                            <?php else: ?>
                                                                <i class="bi bi-shield-x text-muted" title="2FA Disabled"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $admin['status']; ?>">
                                                                <?php echo ucfirst($admin['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                        onclick="viewAdmin(<?php echo $admin['admin_id']; ?>)"
                                                                        title="View Details">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                                        onclick="editAdmin(<?php echo $admin['admin_id']; ?>)"
                                                                        title="Edit">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="deleteAdmin(<?php echo $admin['admin_id']; ?>)"
                                                                        title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">
                                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                        No administrators found. Try adjusting your filters.
                                                    </td>
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

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addAdminForm" method="POST" action="ajax/add_admin.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role_id" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_email" id="sendEmail" checked>
                                    <label class="form-check-label" for="sendEmail">
                                        Send welcome email with login credentials
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add Administrator
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="source/assets/js/bootstrap.js"></script>
    <script src="source/assets/js/app.js"></script>

    <script>
        function viewAdmin(adminId) {
            alert('View admin details: ' + adminId);
            // Implement view functionality
        }

        function editAdmin(adminId) {
            alert('Edit admin: ' + adminId);
            // Implement edit functionality
        }

        function deleteAdmin(adminId) {
            if (confirm('Are you sure you want to delete this administrator? This action cannot be undone.')) {
                // Implement delete functionality
                alert('Admin deleted successfully!');
            }
        }

        function exportAdmins() {
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            window.location.href = `ajax/export_admins.php?${params.toString()}`;
        }

        // Form validation
        document.getElementById('addAdminForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = this.querySelector('[name="password"]').value;
            const confirmPassword = this.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            // Submit form via AJAX
            const formData = new FormData(this);
            fetch('ajax/add_admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Administrator added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>
