<?php
/**
 * Admin Login Page
 * Handles administrator authentication and session management
 */

// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

// ==================== HANDLE LOGIN SUBMISSION ====================
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // Check admin credentials
        $query = "SELECT 
            admin_id,
            CONCAT(first_name, ' ', last_name) as full_name,
            email,
            password_hash,
            role_id,
            status,
            two_factor_enabled,
            failed_login_attempts,
            last_failed_login
        FROM admin_users 
        WHERE email = ? AND status = 'active'";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($admin = mysqli_fetch_assoc($result)) {
            // Check if account is locked due to failed attempts
            if ($admin['failed_login_attempts'] >= 5) {
                $lockout_time = strtotime($admin['last_failed_login']) + (15 * 60); // 15 minutes lockout
                if (time() < $lockout_time) {
                    $remaining_minutes = ceil(($lockout_time - time()) / 60);
                    $error_message = "Account temporarily locked due to too many failed login attempts. Please try again in {$remaining_minutes} minute(s).";
                } else {
                    // Reset failed attempts after lockout period
                    $reset_query = "UPDATE admin_users SET failed_login_attempts = 0 WHERE admin_id = ?";
                    $reset_stmt = mysqli_prepare($conn, $reset_query);
                    mysqli_stmt_bind_param($reset_stmt, 'i', $admin['admin_id']);
                    mysqli_stmt_execute($reset_stmt);
                    mysqli_stmt_close($reset_stmt);
                }
            }
            
            // Verify password
            if (empty($error_message) && password_verify($password, $admin['password_hash'])) {
                // Successful login
                
                // Reset failed login attempts
                $reset_query = "UPDATE admin_users SET 
                    failed_login_attempts = 0,
                    last_login = NOW(),
                    login_count = login_count + 1
                WHERE admin_id = ?";
                $reset_stmt = mysqli_prepare($conn, $reset_query);
                mysqli_stmt_bind_param($reset_stmt, 'i', $admin['admin_id']);
                mysqli_stmt_execute($reset_stmt);
                mysqli_stmt_close($reset_stmt);
                
                // Create session
                $session_token = bin2hex(random_bytes(32));
                $session_query = "INSERT INTO admin_sessions (session_id, admin_id, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))";
                $session_stmt = mysqli_prepare($conn, $session_query);
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                mysqli_stmt_bind_param($session_stmt, 'siss', $session_token, $admin['admin_id'], $ip_address, $user_agent);
                mysqli_stmt_execute($session_stmt);
                mysqli_stmt_close($session_stmt);
                
                // Log admin activity
                // Log admin activity
                logActivity($conn, $admin['admin_id'], 'login', 'auth', 'Admin logged in successfully');
                
                // Set session variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role_id'] = $admin['role_id'];
                $_SESSION['session_token'] = $session_token;
                
                // Set remember me cookie if checked
                if ($remember_me) {
                    setcookie('admin_remember', $session_token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                }
                
                // Check if 2FA is enabled
                if ($admin['two_factor_enabled']) {
                    $_SESSION['2fa_pending'] = true;
                    header('Location: verify_2fa.php');
                } else {
                    // Redirect to dashboard
                    header('Location: admin_dashboard.php');
                }
                exit();
            } else {
                // Failed login - increment failed attempts
                if (empty($error_message)) {
                    $update_query = "UPDATE admin_users SET 
                        failed_login_attempts = failed_login_attempts + 1,
                        last_failed_login = NOW()
                    WHERE admin_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, 'i', $admin['admin_id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    
                    $remaining_attempts = 5 - ($admin['failed_login_attempts'] + 1);
                    if ($remaining_attempts > 0) {
                        $error_message = "Invalid password. You have {$remaining_attempts} attempt(s) remaining.";
                    } else {
                        $error_message = "Account locked due to too many failed attempts. Please try again in 15 minutes.";
                    }
                }
            }
        } else {
            $error_message = "Invalid email or password. Please try again.";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Get demo credentials from database
$demo_query = "SELECT email, CONCAT(first_name, ' ', last_name) as full_name, r.role_name 
    FROM admin_users au
    JOIN roles r ON au.role_id = r.role_id
    WHERE au.status = 'active'
    LIMIT 2";
$demo_result = mysqli_query($conn, $demo_query);
$demo_accounts = [];
while ($row = mysqli_fetch_assoc($demo_result)) {
    $demo_accounts[] = $row;
}

// ==================== VIEW LAYER ====================
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Smart Laptop Advisor</title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="source/assets/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="source/assets/css/app.css">
    <link rel="stylesheet" href="source/assets/css/pages/auth.css">
</head>

<body>
    <div id="auth">
        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <div class="auth-logo">
                        <a href="admin_dashboard.php"><h2 class="text-primary">Smart Laptop Advisor</h2></a>
                    </div>
                    <h1 class="auth-title">Admin Login</h1>
                    <p class="auth-subtitle mb-5">Access the Smart Laptop Advisor Administration Panel</p>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="email" class="form-control form-control-xl" placeholder="Email Address" 
                                   name="email" id="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <div class="form-control-icon">
                                <i class="bi bi-person"></i>
                            </div>
                        </div>
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" class="form-control form-control-xl" placeholder="Password" 
                                   name="password" id="password" required>
                            <div class="form-control-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                        </div>
                        <div class="form-check form-check-lg d-flex align-items-end">
                            <input class="form-check-input me-2" type="checkbox" value="1" name="remember_me" id="remember_me">
                            <label class="form-check-label text-gray-600" for="remember_me">
                                Keep me logged in
                            </label>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary btn-block btn-lg shadow-lg mt-5">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Log in
                        </button>
                    </form>
                    
                    <div class="text-center mt-5 text-lg fs-4">
                        <p class="text-gray-600">Forgot your password? <a href="forgot_password.php" class="font-bold">Reset it</a>.</p>
                    </div>
                    
                    <!-- Demo Credentials -->
                    <?php if (!empty($demo_accounts)): ?>
                    <div class="alert alert-info mt-4">
                        <h6><i class="bi bi-info-circle"></i> Demo Credentials</h6>
                        <div class="row">
                            <?php foreach ($demo_accounts as $index => $account): ?>
                            <div class="col-6">
                                <small><strong><?php echo htmlspecialchars($account['role_name']); ?>:</strong></small><br>
                                <small><?php echo htmlspecialchars($account['email']); ?></small><br>
                                <small class="text-muted">password123</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2">
                            <?php foreach ($demo_accounts as $index => $account): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" 
                                    onclick="fillCredentials('<?php echo htmlspecialchars($account['email']); ?>')">
                                Use <?php echo htmlspecialchars($account['role_name']); ?> Demo
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right" class="d-flex align-items-center justify-content-center" 
                     style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
                    <div class="text-center text-white">
                        <div class="mb-4">
                            <i class="bi bi-laptop" style="font-size: 5rem; opacity: 0.8;"></i>
                        </div>
                        <h2 class="mb-3">Smart Laptop Advisor</h2>
                        <h4 class="mb-4">Administration Panel</h4>
                        <p class="lead mb-4">Manage your laptop recommendation platform with powerful AI-driven insights</p>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="bi bi-cpu-fill" style="font-size: 2rem;"></i>
                                </div>
                                <h6>AI Engine</h6>
                                <small>Smart Recommendations</small>
                            </div>
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="bi bi-chat-dots-fill" style="font-size: 2rem;"></i>
                                </div>
                                <h6>Chatbot</h6>
                                <small>24/7 Support</small>
                            </div>
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="bi bi-graph-up" style="font-size: 2rem;"></i>
                                </div>
                                <h6>Analytics</h6>
                                <small>Data Insights</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="source/assets/js/bootstrap.js"></script>
    
    <script>
        function fillCredentials(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password123';
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.click();
                }
            });
        }, 5000);
    </script>
</body>

</html>

<?php
// Close database connection
mysqli_close($conn);
?>
