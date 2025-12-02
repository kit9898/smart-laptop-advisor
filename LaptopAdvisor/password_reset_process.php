<?php
// Password Reset Process Handler
require_once 'includes/db_connect.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once 'includes/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load email configuration
$email_config = require 'includes/email_config.php';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // REQUEST RESET - Generate token and send email
    if ($action === 'request_reset') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            header("Location: forgot_password.php?status=error&msg=" . urlencode("Please enter your email address"));
            exit();
        }
        
        // Check if user exists
        $sql = "SELECT user_id, full_name FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $full_name = $user['full_name'];
            
            // Generate a secure random token
            $token = bin2hex(random_bytes(32));
            
            // Token expires in 24 hours
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store token in database
            $sql_insert = "INSERT INTO password_reset_tokens (user_id, token, expiry) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iss", $user_id, $token, $expiry);
            
            if ($stmt_insert->execute()) {
                // Create reset link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['PHP_SELF']);
                $reset_link = $protocol . "://" . $host . $path . "/reset_password.php?token=" . $token;
                
                // Send email using PHPMailer
                $mail = new PHPMailer(true);
                
                try {
                    // Development mode check
                    if ($email_config['development_mode']) {
                        // Log to file instead of sending
                        $log_file = __DIR__ . '/password_reset_links.txt';
                        $log_content = "\n\n" . date('Y-m-d H:i:s') . " - Password Reset Request\n";
                        $log_content .= "Email: {$email}\n";
                        $log_content .= "Name: {$full_name}\n";
                        $log_content .= "Reset Link: {$reset_link}\n";
                        $log_content .= "Expires: {$expiry}\n";
                        $log_content .= "-----------------------------------";
                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        
                        header("Location: forgot_password.php?status=email_sent");
                        exit();
                    }
                    
                    // Production mode - send actual email
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = $email_config['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $email_config['smtp_username'];
                    $mail->Password   = $email_config['smtp_password'];
                    $mail->SMTPSecure = $email_config['smtp_encryption'];
                    $mail->Port       = $email_config['smtp_port'];
                    
                    // Recipients
                    $mail->setFrom($email_config['from_email'], $email_config['from_name']);
                    $mail->addAddress($email, $full_name);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = $email_config['reset_subject'];
                    
                    // HTML email body
                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                     color: white; padding: 20px; text-align: center; border-radius: 10px; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 10px; margin-top: 20px; }
                            .button { display: inline-block; padding: 12px 30px; background: #667eea; 
                                     color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🔐 Password Reset Request</h1>
                            </div>
                            <div class='content'>
                                <p>Hi <strong>{$full_name}</strong>,</p>
                                <p>We received a request to reset your password for your Laptop Advisor account.</p>
                                <p>Click the button below to reset your password:</p>
                                <p style='text-align: center;'>
                                    <a href='{$reset_link}' class='button'>Reset Password</a>
                                </p>
                                <p>Or copy and paste this link into your browser:</p>
                                <p style='word-break: break-all; background: #fff; padding: 10px; border-radius: 5px;'>
                                    {$reset_link}
                                </p>
                                <p><strong>⏰ This link will expire in 24 hours.</strong></p>
                                <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                            </div>
                            <div class='footer'>
                                <p>Thanks,<br><strong>Laptop Advisor Team</strong></p>
                                <p>This is an automated email. Please do not reply to this message.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Plain text alternative
                    $mail->AltBody = "Hi {$full_name},\n\n"
                        . "We received a request to reset your password for your Laptop Advisor account.\n\n"
                        . "Visit this link to reset your password:\n{$reset_link}\n\n"
                        . "This link will expire in 24 hours.\n\n"
                        . "If you didn't request this password reset, please ignore this email. "
                        . "Your password will remain unchanged.\n\n"
                        . "Thanks,\nLaptop Advisor Team";
                    
                    $mail->send();
                    header("Location: forgot_password.php?status=email_sent");
                    exit();
                    
                } catch (Exception $e) {
                    // Log error and show user-friendly message
                    error_log("Password reset email failed: " . $mail->ErrorInfo);
                    header("Location: forgot_password.php?status=error&msg=" . urlencode("Failed to send email. Please try again later."));
                    exit();
                }
            } else {
                header("Location: forgot_password.php?status=error&msg=" . urlencode("Failed to generate reset token"));
                exit();
            }
            $stmt_insert->close();
        } else {
            // For security, don't reveal if email exists or not
            // Show success message anyway
            header("Location: forgot_password.php?status=email_sent");
            exit();
        }
        $stmt->close();
    }
}

// If accessed directly, redirect to forgot password page
header("Location: forgot_password.php");
exit();
?>
