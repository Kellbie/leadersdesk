<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'request_reset') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Create password_resets table if it doesn't exist
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(100) NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (token),
                    INDEX (email)
                )");
                
                // Delete any existing tokens for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Save new token to database
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);
                
                // Send email with reset link
                $reset_link = "https://kelto.tech/leaderdesk/forgot-password.php?step=reset&email=" . urlencode($email) . "&token=" . $token;
                
                // Professional HTML Email Template
                $to = $email;
                $subject = "Reset your LeaderDesk password";
                
                // Email headers
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: LeaderDesk <noreply@leaderdesk.com>" . "\r\n";
                $headers .= "Reply-To: support@leaderdesk.com" . "\r\n";
                
                // Beautiful email template
                $message = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Reset Your Password</title>
                </head>
                <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 40px 20px;">
                        <tr>
                            <td align="center">
                                <table width="100%" max-width="480px" cellpadding="0" cellspacing="0" border="0" style="max-width: 480px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;">
                                    <!-- Header with Logo -->
                                    <tr>
                                        <td style="background: #1a1a1a; padding: 40px 30px; text-align: center;">
                                            <h1 style="color: white; font-size: 32px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">Leader<span style="color: #ffffff; opacity: 0.8;">Desk</span></h1>
                                            <p style="color: #a0a0a0; font-size: 14px; margin: 8px 0 0 0;">Password Reset Request</p>
                                        </td>
                                    </tr>
                                    
                                    <!-- Content -->
                                    <tr>
                                        <td style="padding: 40px 30px;">
                                            <h2 style="color: #1a1a1a; font-size: 20px; font-weight: 600; margin: 0 0 8px 0;">Hello ' . htmlspecialchars($user['name']) . ',</h2>
                                            <p style="color: #4a4a4a; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">We received a request to reset the password for your LeaderDesk account. Click the button below to create a new password. This link will expire in 1 hour.</p>
                                            
                                            <!-- Reset Button -->
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
                                                <tr>
                                                    <td align="center">
                                                        <a href="' . $reset_link . '" style="display: inline-block; background: #1a1a1a; color: white; font-size: 16px; font-weight: 600; padding: 14px 32px; text-decoration: none; border-radius: 50px; box-shadow: 0 4px 6px -2px rgba(0, 0, 0, 0.05); transition: all 0.2s;">Reset Password →</a>
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <!-- Alternative Link -->
                                            <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 0 0 16px 0;">Or copy this link into your browser:</p>
                                            <p style="background: #f5f5f5; padding: 12px 16px; border-radius: 12px; font-size: 13px; color: #1a1a1a; word-break: break-all; margin: 0 0 30px 0;">
                                                <a href="' . $reset_link . '" style="color: #1a1a1a; text-decoration: none;">' . $reset_link . '</a>
                                            </p>
                                            
                                            <!-- Security Notice -->
                                            <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; padding: 16px; margin: 20px 0;">
                                                <p style="color: #92400e; font-size: 13px; margin: 0; display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 18px;">🔒</span>
                                                    <span>If you didn\'t request this password reset, you can safely ignore this email. Your account is secure.</span>
                                                </p>
                                            </div>
                                            
                                            <hr style="border: none; border-top: 1px solid #eaeaea; margin: 30px 0 20px 0;">
                                            
                                            <!-- Footer -->
                                            <p style="color: #888; font-size: 12px; line-height: 1.6; text-align: center; margin: 0;">
                                                LeaderDesk · Your MLM Team Management Platform<br>
                                                <a href="https://kelto.tech" style="color: #888; text-decoration: none;">kelto.tech</a> · 
                                                <a href="mailto:support@leaderdesk.com" style="color: #888; text-decoration: none;">support@leaderdesk.com</a>
                                            </p>
                                            <p style="color: #888; font-size: 11px; text-align: center; margin: 20px 0 0 0;">
                                                &copy; ' . date('Y') . ' LeaderDesk. All rights reserved.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
                ';
                
                // Send email
                if (mail($to, $subject, $message, $headers)) {
                    $success = "Password reset instructions have been sent to your email. Please check your inbox.";
                } else {
                    $error = "Failed to send email. Please try again later.";
                }
            } else {
                // Don't reveal that email doesn't exist (security)
                $success = "If an account exists with that email, you'll receive reset instructions.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please enter a new password';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            // Verify token from database
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = 0");
            $stmt->execute([$email, $token]);
            $reset = $stmt->fetch();
            
            if ($reset) {
                // Update password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $email]);
                
                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $stmt->execute([$reset['id']]);
                
                // Delete expired tokens
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
                $stmt->execute();
                
                $_SESSION['success_message'] = "Password reset successfully! You can now login with your new password.";
                header("Location: login.php");
                exit();
            } else {
                $error = 'Invalid or expired reset link. Please request a new one.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LeaderDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .forgot-header {
            background: #1a1a1a;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .forgot-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .forgot-header h1 span {
            opacity: 0.8;
        }

        .forgot-header p {
            opacity: 0.8;
            font-size: 15px;
        }

        .forgot-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
        }

        .btn {
            width: 100%;
            padding: 16px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            background: #1a1a1a;
            color: white;
        }

        .btn:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #f5f5f5;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .back-to-login {
            margin-top: 24px;
            text-align: center;
        }

        .back-to-login a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .back-to-login a:hover {
            color: #1a1a1a;
        }

        .info-note {
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            padding: 16px;
            background: #f5f5f5;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #eaeaea;
        }

        .info-note strong {
            color: #1a1a1a;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <h1>Leader<span>Desk</span></h1>
            <p><?php echo $step == 'request' ? 'Reset your password' : 'Create new password'; ?></p>
        </div>
        
        <div class="forgot-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <a href="login.php" class="btn btn-secondary">Return to Login</a>
            <?php endif; ?>

            <?php if ($step == 'request' && !$success): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request_reset">
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                    </div>
                    
                    <button type="submit" class="btn">Send Reset Instructions</button>
                </form>

            <?php elseif ($step == 'reset' && !$success): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="back-to-login">
                <a href="login.php">
                    <span>←</span> Back to Login
                </a>
            </div>

            <?php if ($step == 'request' && !$success): ?>
                <div class="info-note">
                    <strong>🔒 Secure Reset</strong><br>
                    We'll send a secure link to your email. The link expires in 1 hour.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>