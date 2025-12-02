<?php
// Forgot Password Page
require_once 'includes/db_connect.php';

// Redirect logged-in users to their profile
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$message = '';
$message_type = '';

// Check for status messages from the processing script
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'email_sent':
            $message = 'Password reset instructions have been sent to your email address.';
            $message_type = 'success';
            break;
        case 'user_not_found':
            $message = 'No account found with that email address.';
            $message_type = 'danger';
            break;
        case 'error':
            $message = $_GET['msg'] ?? 'An error occurred. Please try again.';
            $message_type = 'danger';
            break;
    }
}

include 'includes/header.php';
?>

<style>
/* Forgot Password Page Styles */
.forgot-password-wrapper {
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 15s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.3; }
}

.page-header-content {
    position: relative;
    z-index: 1;
}

.page-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.page-header p {
    margin: 0;
    font-size: 1.1rem;
    color: rgba(255,255,255,0.9);
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert-success {
    background: #d1e7dd;
    color: #0f5132;
    border-left: 4px solid #0f5132;
}

.alert-danger {
    background: #f8d7da;
    color: #842029;
    border-left: 4px solid #842029;
}

/* Form Card */
.form-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.info-box {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
}

.info-box h3 {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-box p {
    margin: 0;
    color: #6c757d;
    font-size: 0.95rem;
    line-height: 1.6;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.form-group input[type="email"] {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
    font-family: inherit;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

/* Buttons */
.btn {
    padding: 14px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    width: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    margin-top: 10px;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
}

/* Links */
.back-link {
    text-align: center;
    margin-top: 20px;
}

.back-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.back-link a:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 768px) {
    .forgot-password-wrapper {
        padding: 15px;
    }
    
    .page-header {
        padding: 30px 20px;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .form-card {
        padding: 25px 20px;
    }
}
</style>

<div class="forgot-password-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1>🔐 Forgot Password</h1>
            <p>Reset your password in just a few steps</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <span><?php echo $message_type === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($message_type !== 'success'): ?>
        <!-- Form Card -->
        <div class="form-card">
            <!-- Info Box -->
            <div class="info-box">
                <h3>
                    <span>ℹ️</span>
                    <span>How Password Reset Works</span>
                </h3>
                <p>
                    Enter your email address below and we'll send you a link to reset your password. 
                    The link will be valid for 24 hours.
                </p>
            </div>

            <!-- Form -->
            <form action="password_reset_process.php" method="post" id="forgotPasswordForm">
                <input type="hidden" name="action" value="request_reset">
                
                <div class="form-group">
                    <label for="email">
                        📧 Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required
                           placeholder="Enter your registered email address"
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>📨</span>
                    <span>Send Reset Link</span>
                </button>

                <a href="login.php" class="btn btn-secondary">
                    <span>←</span>
                    <span>Back to Login</span>
                </a>
            </form>

            <div class="back-link">
                Don't have an account? <a href="register.php">Sign up now</a>
            </div>
        </div>
    <?php else: ?>
        <div class="form-card">
            <div class="back-link">
                <a href="login.php" class="btn btn-primary">Return to Login</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Smooth scroll to alerts
window.addEventListener('load', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php include 'includes/footer.php'; ?>