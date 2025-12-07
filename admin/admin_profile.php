<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email is taken by another admin
        $check_query = "SELECT admin_id FROM admin_users WHERE email = ? AND admin_id != ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'si', $email, $admin_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error_message = "Email address is already in use.";
        } else {
            // Update profile
            $update_query = "UPDATE admin_users SET first_name = ?, last_name = ?, email = ? WHERE admin_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'sssi', $first_name, $last_name, $email, $admin_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Profile updated successfully.";
                // Update session
                $_SESSION['admin_name'] = $first_name . ' ' . $last_name;
                $_SESSION['admin_email'] = $email;
                
                logActivity($conn, $admin_id, 'update', 'admin_users', 'Updated personal profile');
            } else {
                $error_message = "Error updating profile: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        $query = "SELECT password_hash FROM admin_users WHERE admin_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        
        if (password_verify($current_password, $admin['password_hash'])) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE admin_users SET password_hash = ? WHERE admin_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, 'si', $new_hash, $admin_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Password changed successfully.";
                logActivity($conn, $admin_id, 'update', 'admin_users', 'Changed password');
            } else {
                $error_message = "Error changing password.";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Incorrect current password.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch current data
$query = "SELECT first_name, last_name, email, role_id FROM admin_users WHERE admin_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Laptop Advisor Admin</title>
    
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
                            <h3>My Profile</h3>
                            <p class="text-subtitle text-muted">Manage your account settings and preferences</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Profile</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12 col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-center align-items-center flex-column">
                                        <div class="avatar avatar-2xl">
                                            <img src="source/assets/images/faces/1.jpg" alt="Avatar">
                                        </div>
                                        <h3 class="mt-3"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                                        <p class="text-small">Administrator</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <form action="" method="post">
                                        <input type="hidden" name="update_profile" value="1">
                                        <div class="form-group">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Your Name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Your Last Name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" name="email" id="email" class="form-control" placeholder="Your Email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Change Password</h5>
                                </div>
                                <div class="card-body">
                                    <form action="" method="post">
                                        <input type="hidden" name="change_password" value="1">
                                        <div class="form-group my-2">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Enter current password" required>
                                        </div>
                                        <div class="form-group my-2">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" required>
                                        </div>
                                        <div class="form-group my-2">
                                            <label for="confirm_password" class="form-label">Confirm Password</label>
                                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                                        </div>

                                        <div class="form-group my-2 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">Change Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <?php include 'includes/admin_footer.php'; ?>
        </div>
    </div>
    <script src="source/assets/js/bootstrap.js"></script>
    <script src="source/assets/js/app.js"></script>
</body>
</html>
