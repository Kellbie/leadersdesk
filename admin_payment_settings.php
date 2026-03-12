<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Payment Settings";
$message = '';
$error = '';

// Get current payment settings
$stmt = $pdo->query("SELECT * FROM payment_settings WHERE id = 1");
$settings = $stmt->fetch();

if (!$settings) {
    // Insert default settings
    $pdo->exec("INSERT INTO payment_settings (id, payment_enabled, trial_enabled) VALUES (1, 0, 1)");
    $stmt = $pdo->query("SELECT * FROM payment_settings WHERE id = 1");
    $settings = $stmt->fetch();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_payment_settings') {
        $payment_enabled = isset($_POST['payment_enabled']) ? 1 : 0;
        $trial_enabled = isset($_POST['trial_enabled']) ? 1 : 0;
        $monthly_price = floatval($_POST['monthly_price'] ?? 29);
        $trial_days = intval($_POST['trial_days'] ?? 60);
        $grace_period_days = intval($_POST['grace_period_days'] ?? 7);
        
        $old_settings = $settings;
        
        $stmt = $pdo->prepare("UPDATE payment_settings SET 
            payment_enabled = ?, 
            trial_enabled = ?, 
            monthly_price = ?, 
            trial_days = ?, 
            grace_period_days = ?,
            updated_by = ? 
            WHERE id = 1");
        $stmt->execute([$payment_enabled, $trial_enabled, $monthly_price, $trial_days, $grace_period_days, $current_user['id']]);
        
        // If payment was just enabled, notify all teams
        if ($old_settings['payment_enabled'] == 0 && $payment_enabled == 1) {
            // Get all teams
            $teams = $pdo->query("SELECT id, team_name FROM teams")->fetchAll();
            
            foreach ($teams as $team) {
                // Get all team members
                $members = $pdo->prepare("SELECT id FROM users WHERE team_id = ?");
                $members->execute([$team['id']]);
                
                foreach ($members as $member) {
                    $content = "Payment system has been enabled. Your subscription will start soon. Monthly price: $$monthly_price";
                    $link = "billing.php";
                    
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, content, link, type, created_at) 
                        VALUES (?, ?, 'Payment System Enabled', ?, ?, ?, 'system', NOW())");
                    $stmt->execute([$team['id'], $member['id'], $content, $content, $link]);
                }
            }
            
            // Update all teams with new trial end dates based on settings
            $stmt = $pdo->prepare("UPDATE teams SET 
                trial_end_date = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                payment_due_date = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                payment_status = 'trial'
                WHERE payment_status = 'trial'");
            $stmt->execute([$trial_days, $trial_days + $grace_period_days]);
        }
        
        $_SESSION['success_message'] = "Payment settings updated successfully!";
        header("Location: admin_payment_settings.php");
        exit();
    }
    
    elseif ($_POST['action'] == 'process_expired_trials') {
        // Find all expired trials
        $stmt = $pdo->query("SELECT t.id, t.team_name, t.trial_end_date 
            FROM teams t 
            WHERE t.payment_status = 'trial' 
            AND t.trial_end_date < CURDATE()");
        $expired_teams = $stmt->fetchAll();
        
        $count = 0;
        foreach ($expired_teams as $team) {
            // Update team status to expired
            $stmt = $pdo->prepare("UPDATE teams SET payment_status = 'expired', subscription_status = 'expired' WHERE id = ?");
            $stmt->execute([$team['id']]);
            
            // Get team leader
            $leader = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
            $leader->execute([$team['id']]);
            $leader_id = $leader->fetchColumn();
            
            if ($leader_id) {
                $content = "Your trial period has expired. Please upgrade to continue using all features.";
                $link = "billing.php";
                $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, content, link, type, created_at) 
                    VALUES (?, ?, 'Trial Expired', ?, ?, ?, 'system', NOW())");
                $stmt->execute([$team['id'], $leader_id, $content, $content, $link]);
            }
            $count++;
        }
        
        $_SESSION['success_message'] = "Processed $count expired trials.";
        header("Location: admin_payment_settings.php");
        exit();
    }
}

// Get all teams with payment status
$stmt = $pdo->query("SELECT t.*, 
    COUNT(u.id) as member_count,
    SUM(CASE WHEN u.role = 'team_leader' THEN 1 ELSE 0 END) as leader_count
    FROM teams t
    LEFT JOIN users u ON t.id = u.team_id
    GROUP BY t.id
    ORDER BY t.payment_status, t.trial_end_date");
$teams = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Settings - LeaderDesk Admin</title>
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
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: #1a1a1a;
            color: white;
            padding: 24px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .admin-logo {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }

        .admin-logo span {
            color: #ef4444;
            font-size: 11px;
            margin-left: 6px;
            background: #333;
            padding: 3px 8px;
            border-radius: 100px;
        }

        .admin-nav {
            list-style: none;
        }

        .admin-nav li {
            margin-bottom: 6px;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            color: #999;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            gap: 10px;
            font-size: 14px;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            background: #333;
            color: white;
        }

        .admin-nav a.active {
            border-left: 3px solid #ef4444;
        }

        .back-to-app {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }

        .back-to-app a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ef4444;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
        }

        .back-to-app a:hover {
            background: #333;
        }

        .admin-main {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eaeaea;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .page-title p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 24px;
        }

        .settings-card.full-width {
            grid-column: 1 / -1;
        }

        .settings-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eaeaea;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1a1a1a;
            text-transform: uppercase;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-trial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-active {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-suspended {
            background: #f1f5f9;
            color: #475569;
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .teams-table th {
            text-align: left;
            padding: 12px 16px;
            background: #f5f5f5;
            font-weight: 600;
            font-size: 12px;
            color: #666;
        }

        .teams-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #eaeaea;
            font-size: 13px;
        }

        .info-note {
            background: #e0f2fe;
            color: #0369a1;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-main {
                margin-left: 0;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                LeaderDesk<span>ADMIN</span>
            </div>
            
            <ul class="admin-nav">
                <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
                <li><a href="admin_teams.php">👥 Teams</a></li>
                <li><a href="admin_users.php">👤 Users</a></li>
                <li><a href="admin_subscriptions.php">💳 Subscriptions</a></li>
                <li><a href="admin_payment_settings.php" class="active">💰 Payment Settings</a></li>
                <li><a href="admin_announcements.php">📢 Announcements</a></li>
                <li><a href="admin_settings.php">⚙️ Settings</a></li>
            </ul>
            
            <div class="back-to-app">
                <a href="logout.php">
                    <span>🚪</span>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Payment Settings</h1>
                    <p>Control payment and trial features across the platform</p>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Payment Settings -->
            <div class="settings-grid">
                <div class="settings-card">
                    <h2>Payment Configuration</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_payment_settings">
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="payment_enabled" id="payment_enabled" <?php echo $settings['payment_enabled'] ? 'checked' : ''; ?>>
                            <label for="payment_enabled">Enable Payment System</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="trial_enabled" id="trial_enabled" <?php echo $settings['trial_enabled'] ? 'checked' : ''; ?>>
                            <label for="trial_enabled">Enable Free Trials</label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Monthly Price ($)</label>
                            <input type="number" name="monthly_price" class="form-input" value="<?php echo $settings['monthly_price']; ?>" min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Trial Period (days)</label>
                            <input type="number" name="trial_days" class="form-input" value="<?php echo $settings['trial_days']; ?>" min="0" max="365">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Grace Period After Trial (days)</label>
                            <input type="number" name="grace_period_days" class="form-input" value="<?php echo $settings['grace_period_days']; ?>" min="0" max="30">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h2>Quick Actions</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="process_expired_trials">
                        <p style="margin-bottom: 16px; color: #666;">Process all teams with expired trials and send notifications.</p>
                        <button type="submit" class="btn btn-warning">Process Expired Trials</button>
                    </form>
                    
                    <hr style="margin: 20px 0; border: 1px solid #eaeaea;">
                    
                    <div class="info-note">
                        <strong>Current Status:</strong><br>
                        Payment System: <?php echo $settings['payment_enabled'] ? '✅ Enabled' : '❌ Disabled'; ?><br>
                        Free Trials: <?php echo $settings['trial_enabled'] ? '✅ Enabled' : '❌ Disabled'; ?><br>
                        Monthly Price: $<?php echo $settings['monthly_price']; ?><br>
                        Trial Period: <?php echo $settings['trial_days']; ?> days
                    </div>
                </div>
            </div>

            <!-- Teams Payment Status -->
            <div class="settings-card full-width">
                <h2>Teams Payment Status</h2>
                
                <table class="teams-table">
                    <thead>
                        <tr>
                            <th>Team</th>
                            <th>Status</th>
                            <th>Trial End</th>
                            <th>Payment Due</th>
                            <th>Members</th>
                            <th>Leaders</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $team['payment_status']; ?>">
                                        <?php echo ucfirst($team['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $team['trial_end_date'] ? date('M d, Y', strtotime($team['trial_end_date'])) : '-'; ?></td>
                                <td><?php echo $team['payment_due_date'] ? date('M d, Y', strtotime($team['payment_due_date'])) : '-'; ?></td>
                                <td><?php echo $team['member_count']; ?></td>
                                <td><?php echo $team['leader_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
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