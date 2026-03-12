<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    // If already logged in, redirect based on role
    if ($_SESSION['user_role'] == 'super_admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';

// Check for success message from password reset
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = $_POST['login'] ?? ''; // Can be email or phone
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check if login is email or phone
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        }
        
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['team_id'] = $user['team_id'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'login', 'User logged in', 0)");
                $stmt->execute([$user['team_id'], $user['id']]);
            } catch (Exception $e) {}
            
            // Check for pending team invite
            if (isset($_SESSION['invite_team_id'])) {
                $invite_team_id = $_SESSION['invite_team_id'];
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND team_id = ?");
                $stmt->execute([$user['id'], $invite_team_id]);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?");
                    $stmt->execute([$invite_team_id, $user['id']]);
                    
                    $_SESSION['team_id'] = $invite_team_id;
                    
                    $stmt = $pdo->prepare("INSERT IGNORE INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Member', 'member', CURDATE())");
                    $stmt->execute([$user['id'], $invite_team_id]);
                    
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'joined_via_link', 'Joined team via invite link after login', 5)");
                    $stmt->execute([$invite_team_id, $user['id']]);
                    
                    // Notify team leader
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
                    $stmt->execute([$invite_team_id]);
                    $leader = $stmt->fetch();
                    
                    if ($leader) {
                        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Team Member Joined', ?, 'member', NOW())");
                        $stmt->execute([$invite_team_id, $leader['id'], "A new member has joined your team via invite link."]);
                    }
                    
                    $_SESSION['success_message'] = "You have been added to the team.";
                }
                
                unset($_SESSION['invite_team_id']);
            }
            
            if ($user['role'] == 'super_admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = 'Invalid login credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - LeaderDesk</title>
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

        .login-container {
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .login-header {
            background: #1a1a1a;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
            opacity: 0.8;
            font-size: 15px;
        }

        .login-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
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
        }

        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox span {
            font-size: 14px;
            color: #666;
        }

        .forgot-link {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-link:hover {
            color: #1a1a1a;
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

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
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

        .register-link {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        .register-link p {
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>
        
        <div class="login-form">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['invite']) && $_GET['invite'] == 1): ?>
                <div class="alert" style="background: #fef3c7; color: #92400e; border-color: #fde68a;">
                    You've been invited to join a team! Please log in or register to continue.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" name="login" class="form-input" placeholder="Enter your email or phone number" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                    <div class="form-hint">You can use your email or phone number to log in</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn">Sign In →</button>
                
                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Create account</a></p>
                </div>
            </form>
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