<?php
// It's a public page, so no auth_check is needed.
require_once 'includes/db_connect.php'; 
$error = '';

// If a user is already logged in, redirect them to their profile page.
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Prepare a statement to fetch the user's data. This is more readable.
        $sql = "SELECT user_id, full_name, password_hash FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify the password against the stored hash
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct. Store the correct session variables.
                
                // --- CRITICAL FIXES ARE HERE ---
                $_SESSION["user_id"] = $user['user_id'];
                $_SESSION["full_name"] = $user['full_name']; // Use "full_name" to match the rest of the site
                
                // Redirect user to their profile page upon successful login
                header("location: profile.php");
                exit();
            } else {
                // Invalid password
                $error = "The email or password you entered was not valid.";
            }
        } else {
            // No account found with that email
            $error = "The email or password you entered was not valid.";
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="form-container content-box">
    <h2>Login</h2>
    <p>Please fill in your credentials to login.</p>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form action="login.php" method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>    
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <!-- FORGOT PASSWORD LINK ADDED HERE -->
        <div style="text-align: right; margin-bottom: 20px;">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Login</button>
        </div>
        <p style="text-align: center; margin-top: 20px;">
            Don't have an account? <a href="register.php">Sign up now</a>.
        </p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>