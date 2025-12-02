<?php
/**
 * Email Configuration for Password Reset
 * 
 * SETUP INSTRUCTIONS:
 * 1. Replace YOUR_EMAIL@gmail.com with your actual Gmail address
 * 2. Replace YOUR_APP_PASSWORD with your Gmail App Password
 * 
 * HOW TO GET GMAIL APP PASSWORD:
 * 1. Go to https://myaccount.google.com/security
 * 2. Enable 2-Step Verification if not already enabled
 * 3. Go to https://myaccount.google.com/apppasswords
 * 4. Create a new app password for "Mail"
 * 5. Copy the 16-character password and paste it below
 */

return [
    // SMTP Settings
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls', // or 'ssl' for port 465
    
    // Email Credentials
    'smtp_username' => 'chansh-am22@student.tarc.edu.my',  // ← CHANGE THIS to your Gmail address
    'smtp_password' => 'rrpe igbz ywzf bxpp',      // ← CHANGE THIS to your Gmail App Password
    
    // From Email Settings
    'from_email' => 'chansh-am22@student.tarc.edu.my',      // ← CHANGE THIS to your Gmail address
    'from_name' => 'Laptop Advisor',
    
    // Email Content
    'reset_subject' => 'Password Reset Request - Laptop Advisor',
    
    // Development Mode (set to false for production)
    'development_mode' => false,  // When true, logs to file instead of sending email
];
