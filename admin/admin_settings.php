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

// Initial dummy values (in a real app, these would come from DB or cookies)
$notifications_enabled = isset($_COOKIE['admin_notifications']) ? $_COOKIE['admin_notifications'] : '1';
$theme 				   = isset($_COOKIE['admin_theme']) ? $_COOKIE['admin_theme'] : 'light';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle saving settings (using cookies for simulation as no settings table exists yet)
    if (isset($_POST['save_settings'])) {
        $notifications = isset($_POST['notifications']) ? '1' : '0';
        $theme = $_POST['theme'];
        
        // Save to cookies for 30 days
        setcookie('admin_notifications', $notifications, time() + (86400 * 30), "/");
        setcookie('admin_theme', $theme, time() + (86400 * 30), "/");
        
        $notifications_enabled = $notifications;
        
        $success_message = "Settings saved successfully.";
        logActivity($conn, $admin_id, 'update', 'settings', 'Updated personal preferences');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Smart Laptop Advisor Admin</title>
    
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
                            <h3>Settings</h3>
                            <p class="text-subtitle text-muted">Customize your admin experience</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Settings</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12 col-md-8 offset-md-2">
                             <form action="" method="post">
                                <input type="hidden" name="save_settings" value="1">
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">General Preferences</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group row align-items-center mb-4">
                                            <label class="col-sm-3 col-form-label">Theme</label>
                                            <div class="col-sm-9">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?php echo ($theme == 'light') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="themeLight">
                                                        <i class="bi bi-sun me-1"></i> Light
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?php echo ($theme == 'dark') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="themeDark">
                                                        <i class="bi bi-moon me-1"></i> Dark
                                                    </label>
                                                </div>
                                                <small class="d-block text-muted mt-2">Choose your preferred interface appearance.</small>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group row align-items-center">
                                            <label class="col-sm-3 col-form-label">Notifications</label>
                                            <div class="col-sm-9">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="notifications" name="notifications" <?php echo ($notifications_enabled == '1') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notifications">Enable Email Notifications</label>
                                                </div>
                                                <small class="d-block text-muted mt-2">Receive daily digest summaries of platform activity.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-light-secondary me-1 mb-1">Reset</button>
                                    <button type="submit" class="btn btn-primary me-1 mb-1">Save Changes</button>
                                </div>
                            </form>
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
