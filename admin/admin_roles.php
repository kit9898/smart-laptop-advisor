<?php
/**
 * Role & Permission Management Page
 * Module E: User & System Administration
 * Manage user roles and assign permissions
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

// Fetch role statistics
$stats = [
    'total_roles' => 0,
    'active_roles' => 0,
    'total_permissions' => 0,
    'custom_roles' => 0
];

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_system_role = 0 THEN 1 ELSE 0 END) as custom
FROM roles";

$result = mysqli_query($conn, $stats_query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $stats['total_roles'] = $row['total'];
    $stats['active_roles'] = $row['active'];
    $stats['custom_roles'] = $row['custom'];
}

$permissions_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM permissions");
if ($permissions_count && $row = mysqli_fetch_assoc($permissions_count)) {
    $stats['total_permissions'] = $row['count'];
}

// Fetch all roles
$roles_query = "SELECT 
    r.role_id,
    r.role_name,
    r.role_code,
    r.description,
    r.is_system_role,
    r.status,
    r.created_at,
    COUNT(DISTINCT rp.permission_id) as permission_count,
    COUNT(DISTINCT au.admin_id) as user_count
FROM roles r
LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
LEFT JOIN admin_users au ON r.role_id = au.role_id
GROUP BY r.role_id
ORDER BY r.is_system_role DESC, r.role_name";

$roles_result = mysqli_query($conn, $roles_query);
$roles = [];
while ($row = mysqli_fetch_assoc($roles_result)) {
    $roles[] = $row;
}

// Fetch all permissions grouped by module
$permissions_query = "SELECT 
    permission_id,
    permission_name,
    permission_code,
    module,
    description
FROM permissions
ORDER BY module, permission_name";

$permissions_result = mysqli_query($conn, $permissions_query);
$permissions_by_module = [];
while ($row = mysqli_fetch_assoc($permissions_result)) {
    $module = $row['module'];
    if (!isset($permissions_by_module[$module])) {
        $permissions_by_module[$module] = [];
    }
    $permissions_by_module[$module][] = $row;
}

// Fetch role-permission mapping
$role_perms_query = "SELECT role_id, permission_id, access_level FROM role_permissions";
$role_perms_result = mysqli_query($conn, $role_perms_query);
$role_permissions_map = [];
while ($row = mysqli_fetch_assoc($role_perms_result)) {
    $role_permissions_map[$row['role_id']][$row['permission_id']] = $row['access_level'];
}

// ==================== VIEW LAYER ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role &amp; Permission Management - Smart Laptop Advisor</title>
    
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
        .permission-matrix {
        .permission-matrix table {
            min-width: 800px;
        }
        .permission-cell {
            text-align: center;
            cursor: pointer;
            padding: 0.5rem;
        }
        .permission-cell i {
            font-size: 1.2rem;
        }
        .access-none { color: #dc3545; }
        .access-read { color: #ffc107; }
        .access-write { color: #0dcaf0; }
        .access-full { color: #198754; }
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .role-system {
            background-color: #e7f3ff;
            color: #0056b3;
        }
        .role-custom {
            background-color: #fff3cd;
            color: #856404;
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
        .module-header {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 0.75rem;
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
                            <h3>Role & Permission Management</h3>
                            <p class="text-subtitle text-muted">Manage roles and assign permissions</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Roles</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Role Statistics -->
                <div class="row">
                    <div class="col-6 col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-3 py-4-5">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="stats-icon purple">
                                            <i class="iconly-boldShield-Done"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Total Roles</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['total_roles']; ?></h6>
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
                                            <i class="iconly-boldTicket-Star"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Active Roles</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['active_roles']; ?></h6>
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
                                            <i class="iconly-boldSetting"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Permissions</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['total_permissions']; ?></h6>
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
                                            <i class="iconly-boldEdit-Square"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="text-muted font-semibold">Custom Roles</h6>
                                        <h6 class="font-extrabold mb-0"><?php echo $stats['custom_roles']; ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Management Table -->
                <section class="section mt-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">Available Roles</h4>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                                    <i class="bi bi-plus-circle"></i> Add New Role
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Role Name</th>
                                            <th>Description</th>
                                            <th>Type</th>
                                            <th>Permissions</th>
                                            <th>Users</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $role): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($role['role_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($role['role_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></td>
                                                <td>
                                                    <?php if ($role['is_system_role']): ?>
                                                        <span class="role-badge role-system">
                                                            <i class="bi bi-shield-check me-1"></i>System
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="role-badge role-custom">
                                                            <i class="bi bi-pencil me-1"></i>Custom
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light-primary">
                                                        <?php echo $role['permission_count']; ?> permissions
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light-info">
                                                        <?php echo $role['user_count']; ?> users
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $role['status']; ?>">
                                                        <?php echo ucfirst($role['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewRoleDetails(<?php echo $role['role_id']; ?>)"
                                                                title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if (!$role['is_system_role']): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                    onclick="editRole(<?php echo $role['role_id']; ?>)"
                                                                    title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteRole(<?php echo $role['role_id']; ?>)"
                                                                    title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Permission Matrix -->
                <section class="section mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Permission Matrix</h4>
                            <p class="text-muted mb-0 mt-1">
                                <i class="bi bi-info-circle"></i> Click on cells to cycle through access levels
                            </p>
                        </div>
                        <div class="card-body permission-matrix">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 200px;">Permission</th>
                                            <?php foreach ($roles as $role): ?>
                                                <th class="text-center" style="min-width: 100px;">
                                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($permissions_by_module as $module => $perms): ?>
                                            <tr>
                                                <td colspan="<?php echo count($roles) + 1; ?>" class="module-header">
                                                    <i class="bi bi-folder me-2"></i><?php echo htmlspecialchars($module); ?>
                                                </td>
                                            </tr>
                                            <?php foreach ($perms as $permission): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($permission['permission_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($permission['description'] ?? ''); ?></small>
                                                    </td>
                                                    <?php foreach ($roles as $role): ?>
                                                        <?php
                                                        $access_level = $role_permissions_map[$role['role_id']][$permission['permission_id']] ?? 'none';
                                                        $icon = '';
                                                        switch ($access_level) {
                                                            case 'full':
                                                                $icon = '<i class="bi bi-check-circle-fill access-full"></i>';
                                                                break;
                                                            case 'write':
                                                                $icon = '<i class="bi bi-pencil-fill access-write"></i>';
                                                                break;
                                                            case 'read':
                                                                $icon = '<i class="bi bi-eye-fill access-read"></i>';
                                                                break;
                                                            default:
                                                                $icon = '<i class="bi bi-x-circle access-none"></i>';
                                                        }
                                                        ?>
                                                        <td class="permission-cell" 
                                                            onclick="togglePermission(<?php echo $role['role_id']; ?>, <?php echo $permission['permission_id']; ?>, '<?php echo $access_level; ?>')"
                                                            data-role="<?php echo $role['role_id']; ?>"
                                                            data-permission="<?php echo $permission['permission_id']; ?>"
                                                            data-access="<?php echo $access_level; ?>"
                                                            title="<?php echo ucfirst($access_level); ?>">
                                                            <?php echo $icon; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <h6>Legend:</h6>
                                <div class="d-flex gap-3 flex-wrap">
                                    <span><i class="bi bi-x-circle access-none"></i> None</span>
                                    <span><i class="bi bi-eye-fill access-read"></i> Read</span>
                                    <span><i class="bi bi-pencil-fill access-write"></i> Write</span>
                                    <span><i class="bi bi-check-circle-fill access-full"></i> Full Access</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addRoleForm" method="POST" action="ajax/add_role.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="role_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="role_code" required>
                            <small class="text-muted">Lowercase, underscores only (e.g., custom_manager)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Create Role
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
        function viewRoleDetails(roleId) {
            alert('View role details: ' + roleId);
            // Implement view functionality
        }

        function editRole(roleId) {
            alert('Edit role: ' + roleId);
            // Implement edit functionality
        }

        function deleteRole(roleId) {
            if (confirm('Are you sure you want to delete this role? Users assigned to this role will need to be reassigned.')) {
                // Implement delete functionality
                alert('Role deleted successfully!');
            }
        }

        function togglePermission(roleId, permissionId, currentAccess) {
            // Cycle through access levels: none -> read -> write -> full -> none
            const levels = ['none', 'read', 'write', 'full'];
            const currentIndex = levels.indexOf(currentAccess);
            const nextLevel = levels[(currentIndex + 1) % levels.length];
            
            // Update via AJAX
            fetch('ajax/update_permission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    role_id: roleId,
                    permission_id: permissionId,
                    access_level: nextLevel
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const cell = event.currentTarget;
                    cell.setAttribute('data-access', nextLevel);
                    cell.setAttribute('title', nextLevel.charAt(0).toUpperCase() + nextLevel.slice(1));
                    
                    // Update icon
                    let icon = '';
                    switch (nextLevel) {
                        case 'full':
                            icon = '<i class="bi bi-check-circle-fill access-full"></i>';
                            break;
                        case 'write':
                            icon = '<i class="bi bi-pencil-fill access-write"></i>';
                            break;
                        case 'read':
                            icon = '<i class="bi bi-eye-fill access-read"></i>';
                            break;
                        default:
                            icon = '<i class="bi bi-x-circle access-none"></i>';
                    }
                    cell.innerHTML = icon;
                } else {
                    alert('Error updating permission: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>
