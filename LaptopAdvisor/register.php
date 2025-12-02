<?php
require_once 'includes/db_connect.php'; 

$errors = [];
$success = '';

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name)) $errors['full_name'] = "Full name is required.";
    if (empty($email)) $errors['email'] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Invalid email format.";
    if (empty($password)) $errors['password'] = "Password is required.";
    elseif (strlen($password) < 6) $errors['password'] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors['confirm_password'] = "Passwords do not match.";

    // Check for existing email if other validation passes
    if (empty($errors)) {
        $sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0){
            $errors['email'] = "An account with this email already exists.";
        }
        $stmt->close();
    }
        
    // Process registration if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $sql_insert = "INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("sss", $full_name, $email, $password_hash);
        
        if($stmt_insert->execute()){
            $success = "Registration successful! You can now <a href='login.php'>log in</a>.";
        } else {
            $errors['form'] = "Something went wrong. Please try again later.";
        }
        $stmt_insert->close();
    }
}

include 'includes/header.php';
?>

<div class="form-container content-box">
    <h2>Create an Account</h2>
    <p>Please fill this form to create an account.</p>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php else: ?>
        <?php if(isset($errors['form'])): ?>
            <div class="alert alert-danger"><?php echo $errors['form']; ?></div>
        <?php endif; ?>

        <form action="register.php" method="post" novalidate>
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <!-- THE FIX: Add a class dynamically with PHP -->
                <input type="text" id="full_name" name="full_name" class="<?php if(isset($errors['full_name'])) echo 'is-invalid'; ?>" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                <?php if(isset($errors['full_name'])): ?>
                    <small class="error-text"><?php echo $errors['full_name']; ?></small>
                <?php endif; ?>
            </div>    
            <div class="form-group">
                <label for="email">Email</label>
                <!-- THE FIX: Add a class dynamically with PHP -->
                <input type="email" id="email" name="email" class="<?php if(isset($errors['email'])) echo 'is-invalid'; ?>" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                 <?php if(isset($errors['email'])): ?>
                    <small class="error-text"><?php echo $errors['email']; ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <!-- THE FIX: Add a class dynamically with PHP -->
                <input type="password" id="password" name="password" class="<?php if(isset($errors['password'])) echo 'is-invalid'; ?>" required>
                 <?php if(isset($errors['password'])): ?>
                    <small class="error-text"><?php echo $errors['password']; ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <!-- THE FIX: Add a class dynamically with PHP -->
                <input type="password" id="confirm_password" name="confirm_password" class="<?php if(isset($errors['confirm_password'])) echo 'is-invalid'; ?>" required>
                 <?php if(isset($errors['confirm_password'])): ?>
                    <small class="error-text"><?php echo $errors['confirm_password']; ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
            <p style="text-align: center; margin-top: 20px;">
                Already have an account? <a href="login.php">Login here</a>.
            </p>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>