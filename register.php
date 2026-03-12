<?php
session_start();
require_once 'config/database.php';
require_once 'includes/countries_states.php';

if (isset($_SESSION['user_id'])) {
    // If already logged in, check if this is an upgrade
    if (isset($_GET['upgrade']) && $_GET['upgrade'] == 1) {
        // Allow upgrade page to load for logged in members
        $current_user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $current_user = $stmt->fetch();
        
        if ($current_user['role'] != 'member') {
            header("Location: dashboard.php");
            exit();
        }
    } else {
        header("Location: dashboard.php");
        exit();
    }
}

$error = '';
$success = '';
$invite_team_id = isset($_SESSION['invite_team_id']) ? $_SESSION['invite_team_id'] : null;
$invite_team_name = '';
$is_upgrade = isset($_GET['upgrade']) && $_GET['upgrade'] == 1;

// Pre-fill data for upgrade
$prefill_name = $_GET['name'] ?? '';
$prefill_email = $_GET['email'] ?? '';
$prefill_phone = $_GET['phone'] ?? '';

// If there's an invite, get the team name
if ($invite_team_id) {
    $stmt = $pdo->prepare("SELECT team_name FROM teams WHERE id = ?");
    $stmt->execute([$invite_team_id]);
    $team = $stmt->fetch();
    $invite_team_name = $team ? $team['team_name'] : '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);
    $is_upgrade_post = isset($_POST['is_upgrade']) ? true : false;
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$agree_terms) {
        $error = 'You must agree to the terms and conditions';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if this is an upgrade (existing member creating their own team)
            if ($is_upgrade_post && isset($_SESSION['user_id'])) {
                // Get the existing user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $existing_user = $stmt->fetch();
                
                if (!$existing_user) {
                    throw new Exception('User not found');
                }
                
                // Create new team
                $team_name = $_POST['team_name'] ?? '';
                $country = $_POST['country'] ?? '';
                $state = $_POST['state'] ?? '';
                
                if (empty($team_name)) {
                    throw new Exception('Team name is required');
                }
                
                // Check if team name exists
                $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
                $stmt->execute([$team_name]);
                if ($stmt->fetch()) {
                    throw new Exception('Team name already exists');
                }
                
                // Create team
                $stmt = $pdo->prepare("INSERT INTO teams (team_name, country, state_province, email, phone, trial_start_date, trial_end_date) VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY))");
                $stmt->execute([$team_name, $country, $state, $email, $phone]);
                $team_id = $pdo->lastInsertId();
                
                // Update existing user to team_leader and move to new team
                $stmt = $pdo->prepare("UPDATE users SET role = 'team_leader', team_id = ?, upgrade_requested = 0 WHERE id = ?");
                $stmt->execute([$team_id, $existing_user['id']]);
                
                // Update member profile
                $stmt = $pdo->prepare("UPDATE member_profiles SET rank = 'Leader', team_id = ? WHERE user_id = ?");
                $stmt->execute([$team_id, $existing_user['id']]);
                
                // Create team branding
                $stmt = $pdo->prepare("INSERT INTO team_branding (team_id, tagline, primary_color, welcome_message) VALUES (?, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!')");
                $stmt->execute([$team_id]);
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'upgrade_to_leader', 'Member upgraded to team leader and created new team', 50)");
                $stmt->execute([$team_id, $existing_user['id']]);
                
                // Notify super admin
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'super_admin'");
                $stmt->execute();
                $admins = $stmt->fetchAll();
                
                foreach ($admins as $admin) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Team Created', ?, 'system', NOW())");
                    $stmt->execute([$team_id, $admin['id'], "Member {$existing_user['name']} has upgraded to team leader and created team: $team_name"]);
                }
                
                $pdo->commit();
                
                // Update session
                $_SESSION['user_role'] = 'team_leader';
                $_SESSION['team_id'] = $team_id;
                
                $_SESSION['success_message'] = "Congratulations! You are now a Team Leader. Your new team '$team_name' has been created.";
                header("Location: dashboard.php");
                exit();
                
            } elseif (isset($_SESSION['invite_team_id'])) {
                // Join existing team (invite)
                $team_id = $_SESSION['invite_team_id'];
                $role = 'member';
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, team_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $email, $phone, $hashed_password, $role, $team_id]);
                $user_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Member', 'member', CURDATE())");
                $stmt->execute([$user_id, $team_id]);
                
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'registered_via_invite', 'Joined team via invite link', 10)");
                $stmt->execute([$team_id, $user_id]);
                
                // Notify team leader
                $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
                $stmt->execute([$team_id]);
                $leader = $stmt->fetch();
                
                if ($leader) {
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Team Member Joined', ?, 'member', NOW())");
                    $stmt->execute([$team_id, $leader['id'], "A new member has joined your team via invite link."]);
                }
                
                unset($_SESSION['invite_team_id']);
                
                $_SESSION['success_message'] = "Welcome to the team! You have successfully registered.";
                
            } else {
                // Regular new team registration
                $team_name = $_POST['team_name'] ?? '';
                $country = $_POST['country'] ?? '';
                $state = $_POST['state'] ?? '';
                
                if (empty($team_name)) {
                    throw new Exception('Team name is required');
                }
                
                $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
                $stmt->execute([$team_name]);
                if ($stmt->fetch()) {
                    throw new Exception('Team name already exists');
                }
                
                $stmt = $pdo->prepare("INSERT INTO teams (team_name, country, state_province, email, phone, trial_start_date, trial_end_date) VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY))");
                $stmt->execute([$team_name, $country, $state, $email, $phone]);
                $team_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, team_id, status) VALUES (?, ?, ?, ?, 'team_leader', ?, 'active')");
                $stmt->execute([$name, $email, $phone, $hashed_password, $team_id]);
                $user_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Leader', 'member', CURDATE())");
                $stmt->execute([$user_id, $team_id]);
                
                $stmt = $pdo->prepare("INSERT INTO team_branding (team_id, tagline, primary_color, welcome_message) VALUES (?, 'Build Your Empire', '#1a1a1a', 'Welcome to our team!')");
                $stmt->execute([$team_id]);
                
                $_SESSION['success_message'] = "Team created successfully! Welcome to LeaderDesk.";
            }
            
            $pdo->commit();
            
            // Log the user in if not already logged in
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_role'] = $role ?? 'team_leader';
                $_SESSION['team_id'] = $team_id;
            }
            
            header("Location: dashboard.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_upgrade ? 'Upgrade to Leader' : ($invite_team_id ? 'Join Team' : 'Register'); ?> - LeaderDesk</title>
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

        .register-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .register-header {
            background: #1a1a1a;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .register-header p {
            opacity: 0.8;
            font-size: 15px;
        }

        .upgrade-badge {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
        }

        .invite-badge {
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
        }

        .register-form {
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

        .form-input,
        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            background: white;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkbox-group {
            margin: 20px 0;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
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

        .checkbox a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
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
            background: #f59e0b;
        }

        .btn-secondary:hover {
            background: #d97706;
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

        .info-note {
            background: #f0f0f0;
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }

        .login-link {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        .login-link p {
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><?php 
                if ($is_upgrade) echo 'Upgrade to Team Leader';
                elseif ($invite_team_id) echo 'Join ' . htmlspecialchars($invite_team_name);
                else echo 'Create Your Account';
            ?></h1>
            <p><?php 
                if ($is_upgrade) echo 'Create your own team and become a leader';
                elseif ($invite_team_id) echo 'You\'ve been invited to join a team!';
                else echo 'Start your 2-month free trial today';
            ?></p>
            
            <?php if ($is_upgrade): ?>
                <div class="upgrade-badge">⬆️ Upgrade to Team Leader</div>
            <?php elseif ($invite_team_id): ?>
                <div class="invite-badge">🎉 You're joining: <?php echo htmlspecialchars($invite_team_name); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="register-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($is_upgrade): ?>
                    <input type="hidden" name="is_upgrade" value="1">
                <?php endif; ?>
                
                <!-- Team Name Field (for new teams) -->
                <?php if (!$invite_team_id): ?>
                    <div class="form-group">
                        <label class="form-label">Team Name *</label>
                        <input type="text" name="team_name" class="form-input" placeholder="e.g., Empowered Leaders" value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <select name="country" id="country" class="form-select" onchange="loadStates()">
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo (isset($_POST['country']) && $_POST['country'] == $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">State/Province</label>
                            <select name="state" id="state" class="form-select">
                                <option value="">Select Country First</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- User Information -->
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" placeholder="Your full name" value="<?php echo htmlspecialchars($_POST['name'] ?? $prefill_name); ?>" <?php echo $is_upgrade ? 'readonly' : ''; ?> required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-input" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? $prefill_email); ?>" <?php echo $is_upgrade ? 'readonly' : ''; ?> required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="+1 (555) 000-0000" value="<?php echo htmlspecialchars($_POST['phone'] ?? $prefill_phone); ?>">
                </div>
                
                <?php if (!$is_upgrade): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-input" placeholder="Create a password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm password" required>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="checkbox-group">
                    <label class="checkbox">
                        <input type="checkbox" name="agree_terms" <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?> required>
                        <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                    </label>
                </div>
                
                <?php if ($is_upgrade): ?>
                    <div class="info-note">
                        <strong>Note:</strong> You are upgrading from a member account to a Team Leader. 
                        You will keep your existing login credentials and create a new team.
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn <?php echo $is_upgrade ? 'btn-secondary' : ''; ?>">
                    <?php 
                    if ($is_upgrade) echo 'Upgrade to Team Leader →';
                    elseif ($invite_team_id) echo 'Join Team →';
                    else echo 'Start Free Trial →';
                    ?>
                </button>
                
                <?php if (!$is_upgrade): ?>
                    <div class="login-link">
                        <p>Already have an account? <a href="login.php">Sign in</a></p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // States data from PHP
        const states = <?php echo json_encode($states); ?>;
        
        function loadStates() {
            const countrySelect = document.getElementById('country');
            const stateSelect = document.getElementById('state');
            const selectedCountry = countrySelect.value;
            
            stateSelect.innerHTML = '<option value="">Select State/Province</option>';
            
            if (selectedCountry && states[selectedCountry]) {
                const countryStates = states[selectedCountry];
                for (const [code, name] of Object.entries(countryStates)) {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    stateSelect.appendChild(option);
                }
                stateSelect.disabled = false;
            } else {
                stateSelect.disabled = true;
                stateSelect.innerHTML = '<option value="">Select Country First</option>';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country');
            if (countrySelect && countrySelect.value) {
                loadStates();
                
                <?php if (isset($_POST['state'])): ?>
                const stateSelect = document.getElementById('state');
                for (let i = 0; i < stateSelect.options.length; i++) {
                    if (stateSelect.options[i].value === '<?php echo $_POST['state']; ?>') {
                        stateSelect.selectedIndex = i;
                        break;
                    }
                }
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>