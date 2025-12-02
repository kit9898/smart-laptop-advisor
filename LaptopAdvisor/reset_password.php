<?php
// Reset Password Page - Set new password using token
require_once 'includes/db_connect.php';

// Redirect logged-in users to their profile
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$token_valid = false;
$user_id = null;

// Validate token if provided
if (!empty($token)) {
    // Check if token exists, is not used, and not expired
    $sql = "SELECT user_id, expiry FROM password_reset_tokens 
            WHERE token = ? AND is_used = 0 AND expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $token_data = $result->fetch_assoc();
        $user_id = $token_data['user_id'];
        $token_valid = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new password reset.";
    }
    $stmt->close();
} else {
    $error = "No reset token provided.";
}

// Process password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password)) {
        $error = "Please enter a new password.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update user's password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql_update = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $password_hash, $user_id);
        
        if ($stmt_update->execute()) {
            // Mark token as used
            $sql_mark = "UPDATE password_reset_tokens SET is_used = 1 WHERE token = ?";
            $stmt_mark = $conn->prepare($sql_mark);
            $stmt_mark->bind_param("s", $token);
            $stmt_mark->execute();
            $stmt_mark->close();
            
            $success = true;
        } else {
            $error = "Failed to update password. Please try again.";
        }
        $stmt_update->close();
    }
}

include 'includes/header.php';
?>

<style>
/* Reset Password Page Styles */
.reset-password-wrapper {
    max-width: 700px;
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

.security-tips {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
}

.security-tips h3 {
    margin: 0 0 15px 0;
    font-size: 1.1rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}

.security-tips ul {
    margin: 0;
    padding-left: 20px;
    color: #6c757d;
}

.security-tips li {
    margin-bottom: 8px;
    font-size: 0.9rem;
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
    display: flex;
    align-items: center;
    gap: 8px;
}

.password-input-wrapper {
    position: relative;
}

.form-group input[type="password"],
.form-group input[type="text"] {
    width: 100%;
    padding: 14px 50px 14px 18px;
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

/* Password Toggle Button */
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    color: #6c757d;
    transition: color 0.3s ease;
    padding: 5px;
}

.password-toggle:hover {
    color: #667eea;
}

/* Password Strength Indicator */
.password-strength {
    margin-top: 10px;
    display: none;
}

.password-strength.show {
    display: block;
}

.strength-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 8px;
}

.strength-bar-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 10px;
}

.strength-weak .strength-bar-fill {
    width: 33%;
    background: #dc3545;
}

.strength-medium .strength-bar-fill {
    width: 66%;
    background: #ffc107;
}

.strength-strong .strength-bar-fill {
    width: 100%;
    background: #28a745;
}

.strength-text {
    font-size: 0.875rem;
    font-weight: 600;
}

.strength-weak .strength-text {
    color: #dc3545;
}

.strength-medium .strength-text {
    color: #ffc107;
}

.strength-strong .strength-text {
    color: #28a745;
}

/* Password Requirements */
.password-requirements {
    margin-top: 10px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 0.85rem;
}

.requirement {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
    color: #6c757d;
}

.requirement.met {
    color: #28a745;
}

.requirement-icon {
    font-size: 0.9rem;
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

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
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
    .reset-password-wrapper {
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

<div class="reset-password-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1>🔑 Reset Password</h1>
            <p>Create a new, secure password for your account</p>
        </div>
    </div>

    <?php if ($success): ?>
        <!-- Success Message -->
        <div class="alert alert-success">
            <span>✅</span>
            <span>Password reset successfully! You can now log in with your new password.</span>
        </div>
        <div class="form-card">
            <a href="login.php" class="btn btn-primary">
                <span>🔐</span>
                <span>Go to Login</span>
            </a>
        </div>
    <?php elseif (!empty($error)): ?>
        <!-- Error Message -->
        <div class="alert alert-danger">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <div class="form-card">
            <a href="forgot_password.php" class="btn btn-primary">
                <span>📨</span>
                <span>Request New Reset Link</span>
            </a>
            <a href="login.php" class="btn btn-secondary">
                <span>←</span>
                <span>Back to Login</span>
            </a>
        </div>
    <?php else: ?>
        <!-- Security Tips -->
        <div class="security-tips">
            <h3>
                <span>🛡️</span>
                <span>Password Security Tips</span>
            </h3>
            <ul>
                <li>Use at least 8 characters with a mix of letters, numbers, and symbols</li>
                <li>Avoid using personal information or common words</li>
                <li>Don't reuse passwords from other accounts</li>
                <li>Consider using a password manager</li>
            </ul>
        </div>

        <!-- Reset Form -->
        <div class="form-card">
            <form method="post" id="resetPasswordForm">
                <!-- New Password -->
                <div class="form-group">
                    <label for="new_password">
                        <span>🆕</span>
                        <span>New Password</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               required
                               placeholder="Enter your new password"
                               oninput="checkPasswordStrength()"
                               autofocus>
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            👁️
                        </button>
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-bar-fill"></div>
                        </div>
                        <div class="strength-text">Password Strength: <span id="strengthLabel">-</span></div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="password-requirements">
                        <div class="requirement" id="req-length">
                            <span class="requirement-icon">○</span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement" id="req-uppercase">
                            <span class="requirement-icon">○</span>
                            <span>One uppercase letter</span>
                        </div>
                        <div class="requirement" id="req-lowercase">
                            <span class="requirement-icon">○</span>
                            <span>One lowercase letter</span>
                        </div>
                        <div class="requirement" id="req-number">
                            <span class="requirement-icon">○</span>
                            <span>One number</span>
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password">
                        <span>✓</span>
                        <span>Confirm New Password</span>
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required
                               placeholder="Re-enter your new password"
                               oninput="checkPasswordMatch()">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            👁️
                        </button>
                    </div>
                    <small id="matchMessage" style="display: none; margin-top: 8px;"></small>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span>🔒</span>
                    <span>Reset Password</span>
                </button>

                <a href="login.php" class="btn btn-secondary">
                    <span>←</span>
                    <span>Back to Login</span>
                </a>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        button.textContent = '🙈';
    } else {
        field.type = 'password';
        button.textContent = '👁️';
    }
}

// Check password strength
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthLabel = document.getElementById('strengthLabel');
    
    if (password.length === 0) {
        strengthDiv.classList.remove('show');
        return;
    }
    
    strengthDiv.classList.add('show');
    
    let strength = 0;
    const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };
    
    // Update requirement indicators
    document.getElementById('req-length').classList.toggle('met', requirements.length);
    document.getElementById('req-length').querySelector('.requirement-icon').textContent = requirements.length ? '✓' : '○';
    
    document.getElementById('req-uppercase').classList.toggle('met', requirements.uppercase);
    document.getElementById('req-uppercase').querySelector('.requirement-icon').textContent = requirements.uppercase ? '✓' : '○';
    
    document.getElementById('req-lowercase').classList.toggle('met', requirements.lowercase);
    document.getElementById('req-lowercase').querySelector('.requirement-icon').textContent = requirements.lowercase ? '✓' : '○';
    
    document.getElementById('req-number').classList.toggle('met', requirements.number);
    document.getElementById('req-number').querySelector('.requirement-icon').textContent = requirements.number ? '✓' : '○';
    
    // Calculate strength
    if (requirements.length) strength++;
    if (requirements.uppercase) strength++;
    if (requirements.lowercase) strength++;
    if (requirements.number) strength++;
    if (requirements.special) strength++;
    
    // Update strength indicator
    strengthDiv.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
    
    if (strength <= 2) {
        strengthDiv.classList.add('strength-weak');
        strengthLabel.textContent = 'Weak';
    } else if (strength <= 4) {
        strengthDiv.classList.add('strength-medium');
        strengthLabel.textContent = 'Medium';
    } else {
        strengthDiv.classList.add('strength-strong');
        strengthLabel.textContent = 'Strong';
    }
    
    checkPasswordMatch();
}

// Check if passwords match
function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchMessage = document.getElementById('matchMessage');
    const submitBtn = document.getElementById('submitBtn');
    
    if (confirmPassword.length === 0) {
        matchMessage.style.display = 'none';
        return;
    }
    
    matchMessage.style.display = 'block';
    
    if (newPassword === confirmPassword) {
        matchMessage.textContent = '✓ Passwords match';
        matchMessage.style.color = '#28a745';
        submitBtn.disabled = false;
    } else {
        matchMessage.textContent = '✗ Passwords do not match';
        matchMessage.style.color = '#dc3545';
        submitBtn.disabled = true;
    }
}

// Form validation
document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('New password must be at least 8 characters long');
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return false;
    }
});

// Smooth scroll to alerts
window.addEventListener('load', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
