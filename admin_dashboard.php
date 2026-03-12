<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check - only super_admin can access
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Admin Dashboard";
$message = '';
$error = '';

// Handle team status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_team_status') {
        $team_id = $_POST['team_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE teams SET subscription_status = ? WHERE id = ?");
        $stmt->execute([$status, $team_id]);
        
        $_SESSION['success_message'] = "Team status updated successfully!";
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_POST['action'] == 'extend_trial') {
        $team_id = $_POST['team_id'];
        $days = $_POST['days'] ?? 30;
        
        $stmt = $pdo->prepare("UPDATE teams SET trial_end_date = DATE_ADD(trial_end_date, INTERVAL ? DAY) WHERE id = ?");
        $stmt->execute([$days, $team_id]);
        
        $_SESSION['success_message'] = "Trial extended by $days days!";
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_POST['action'] == 'send_announcement') {
        $title = $_POST['title'] ?? '';
        $message_content = $_POST['message'] ?? '';
        $target = $_POST['target'] ?? 'all';
        
        if (empty($title) || empty($message_content)) {
            $error = 'Title and message are required';
        } else {
            // Get target users based on selection
            if ($target == 'all') {
                $stmt = $pdo->query("SELECT id, team_id FROM users WHERE status = 'active'");
            } elseif ($target == 'leaders') {
                $stmt = $pdo->query("SELECT id, team_id FROM users WHERE role = 'team_leader' AND status = 'active'");
            } elseif ($target == 'members') {
                $stmt = $pdo->query("SELECT id, team_id FROM users WHERE role = 'member' AND status = 'active'");
            } elseif (strpos($target, 'team_') === 0) {
                $team_id = str_replace('team_', '', $target);
                $stmt = $pdo->prepare("SELECT id, team_id FROM users WHERE team_id = ? AND status = 'active'");
                $stmt->execute([$team_id]);
            }
            
            $users = $stmt->fetchAll();
            $sent_count = 0;
            
            // Send notification to each user
            foreach ($users as $user) {
                $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type) VALUES (?, ?, ?, ?, 'announcement')");
                $stmt->execute([$user['team_id'], $user['id'], $title, $message_content]);
                $sent_count++;
            }
            
            // Log the announcement
            $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'announcement', ?, 0)");
            $stmt->execute([$current_user['team_id'] ?? 1, $current_user['id'], "Sent announcement: $title to $sent_count users"]);
            
            $_SESSION['success_message'] = "Announcement sent to $sent_count users!";
            header("Location: admin_dashboard.php");
            exit();
        }
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Platform Statistics
$stats = [];

// Total teams
$stmt = $pdo->query("SELECT COUNT(*) FROM teams");
$stats['total_teams'] = $stmt->fetchColumn();

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Total team leaders
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'team_leader'");
$stats['total_leaders'] = $stmt->fetchColumn();

// Total members
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
$stats['total_members'] = $stmt->fetchColumn();

// Teams by subscription status
$stmt = $pdo->query("SELECT subscription_status, COUNT(*) as count FROM teams GROUP BY subscription_status");
$subscription_stats = [];
while ($row = $stmt->fetch()) {
    $subscription_stats[$row['subscription_status']] = $row['count'];
}

// Active trials (not expired)
$stmt = $pdo->query("SELECT COUNT(*) FROM teams WHERE subscription_status = 'trial' AND trial_end_date > NOW()");
$stats['active_trials'] = $stmt->fetchColumn();

// Expired trials
$stmt = $pdo->query("SELECT COUNT(*) FROM teams WHERE subscription_status = 'trial' AND trial_end_date <= NOW()");
$stats['expired_trials'] = $stmt->fetchColumn();

// Active subscriptions
$stats['active_subscriptions'] = $subscription_stats['active'] ?? 0;
$stats['suspended_teams'] = $subscription_stats['suspended'] ?? 0;

// Recent activity (platform-wide)
$stmt = $pdo->query("SELECT al.*, u.name, u.email, t.team_name 
                     FROM activity_logs al 
                     JOIN users u ON al.user_id = u.id 
                     JOIN teams t ON al.team_id = t.id 
                     ORDER BY al.created_at DESC LIMIT 20");
$recent_activities = $stmt->fetchAll();

// Get all teams with details
$stmt = $pdo->query("SELECT t.*, 
                     COUNT(u.id) as member_count,
                     SUM(CASE WHEN u.role = 'team_leader' THEN 1 ELSE 0 END) as leader_count,
                     SUM(CASE WHEN u.role = 'member' THEN 1 ELSE 0 END) as regular_members
                     FROM teams t
                     LEFT JOIN users u ON t.id = u.team_id
                     GROUP BY t.id
                     ORDER BY t.created_at DESC");
$teams = $stmt->fetchAll();

// Get subscription summary for chart
$subscription_summary = [
    'active' => $stats['active_subscriptions'],
    'trial' => $stats['active_trials'],
    'expired' => $stats['expired_trials'],
    'suspended' => $stats['suspended_teams']
];

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - LeaderDesk</title>
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

        /* Admin Sidebar */
        .admin-sidebar {
            width: 280px;
            background: #1a1a1a;
            color: white;
            padding: 32px 24px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .admin-logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid #333;
        }

        .admin-logo span {
            color: #ef4444;
            font-size: 12px;
            margin-left: 8px;
            background: #333;
            padding: 4px 8px;
            border-radius: 100px;
        }

        .admin-nav {
            list-style: none;
            flex: 1;
        }

        .admin-nav li {
            margin-bottom: 8px;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #999;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            gap: 12px;
        }

        .admin-nav a:hover {
            background: #333;
            color: white;
        }

        .admin-nav a.active {
            background: #333;
            color: white;
            border-left: 3px solid #ef4444;
        }

        .back-to-app {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }

        .back-to-app a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ef4444;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-to-app a:hover {
            background: #333;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 32px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #666;
        }

        .admin-badge {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
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

        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-sub {
            font-size: 13px;
            color: #888;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .chart-item {
            text-align: center;
            padding: 16px;
            border-radius: 12px;
        }

        .chart-item.active {
            background: #ecfdf5;
        }

        .chart-item.trial {
            background: #fef3c7;
        }

        .chart-item.expired {
            background: #fee2e2;
        }

        .chart-item.suspended {
            background: #f1f5f9;
        }

        .chart-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .chart-label {
            font-size: 14px;
            color: #666;
        }

        /* Teams Section */
        .teams-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 6px 12px;
            border-radius: 100px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: #f5f5f5;
        }

        .filter-tab.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
        }

        .teams-table th {
            text-align: left;
            padding: 16px 12px;
            background: #f5f5f5;
            font-weight: 600;
            font-size: 13px;
            border-radius: 8px 8px 0 0;
        }

        .teams-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #eaeaea;
        }

        .team-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .team-email {
            font-size: 12px;
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-trial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-suspended {
            background: #f1f5f9;
            color: #475569;
        }

        .status-select {
            padding: 8px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            font-size: 13px;
            margin-right: 8px;
        }

        .btn-update {
            background: #1a1a1a;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-update:hover {
            background: #333;
        }

        .btn-extend {
            background: #fef3c7;
            color: #92400e;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 4px;
        }

        .btn-extend:hover {
            background: #fde68a;
        }

        .btn-suspend {
            background: #fee2e2;
            color: #991b1b;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 4px;
        }

        .btn-suspend:hover {
            background: #fecaca;
        }

        /* Announcement Section */
        .announcement-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
        }

        .announcement-form {
            max-width: 600px;
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
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Activity Feed */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .activity-meta {
            font-size: 13px;
            color: #888;
        }

        .team-tag {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 11px;
            margin-left: 8px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .modal-close:hover {
            color: #1a1a1a;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .quick-action-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            text-align: center;
            text-decoration: none;
            color: #1a1a1a;
            transition: all 0.2s;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border-color: #1a1a1a;
        }

        .quick-action-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .quick-action-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .quick-action-desc {
            font-size: 12px;
            color: #666;
        }

        /* Animations */
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

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                z-index: 1000;
                transition: transform 0.3s;
            }

            .admin-sidebar.open {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .teams-table {
                display: block;
                overflow-x: auto;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navigation.php'; ?>
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                LeaderDesk<span>ADMIN</span>
            </div>
            
            <ul class="admin-nav">
                <li><a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">📊 Dashboard</a></li>
                <li><a href="admin_teams.php" class="<?php echo $current_page == 'admin_teams.php' ? 'active' : ''; ?>">👥 Teams</a></li>
                <li><a href="admin_users.php" class="<?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>">👤 Users</a></li>
                <li><a href="admin_subscriptions.php" class="<?php echo $current_page == 'admin_subscriptions.php' ? 'active' : ''; ?>">💳 Subscriptions</a></li>
                <li><a href="admin_announcements.php" class="<?php echo $current_page == 'admin_announcements.php' ? 'active' : ''; ?>">📢 Announcements</a></li>
                <li><a href="admin_settings.php" class="<?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">⚙️ Settings</a></li>
            </ul>
            
            <div class="back-to-app">
                <a href="logout.php">
                    <span>🚪</span>
                    Logout (Back to App)
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Super Admin Dashboard</h1>
                    <p>Platform overview and management</p>
                </div>
                <div class="admin-badge">Super Admin</div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Quick Actions for Super Admin -->
            <div class="quick-actions">
                <a href="admin_teams.php?action=create" class="quick-action-card">
                    <div class="quick-action-icon">➕</div>
                    <div class="quick-action-title">Create Team</div>
                    <div class="quick-action-desc">Add new team to platform</div>
                </a>
                <a href="admin_users.php?action=create" class="quick-action-card">
                    <div class="quick-action-icon">👤</div>
                    <div class="quick-action-title">Add User</div>
                    <div class="quick-action-desc">Create user in any team</div>
                </a>
                <a href="admin_announcements.php" class="quick-action-card">
                    <div class="quick-action-icon">📢</div>
                    <div class="quick-action-title">Announcement</div>
                    <div class="quick-action-desc">Send to all users</div>
                </a>
                <a href="admin_teams.php" class="quick-action-card">
                    <div class="quick-action-icon">🔍</div>
                    <div class="quick-action-title">Manage Teams</div>
                    <div class="quick-action-desc">View all teams</div>
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Teams</div>
                    <div class="stat-value"><?php echo $stats['total_teams']; ?></div>
                    <div class="stat-sub">Across platform</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-sub"><?php echo $stats['total_leaders']; ?> leaders • <?php echo $stats['total_members']; ?> members</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Active Trials</div>
                    <div class="stat-value"><?php echo $stats['active_trials']; ?></div>
                    <div class="stat-sub"><?php echo $stats['expired_trials']; ?> expired</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Active Subs</div>
                    <div class="stat-value"><?php echo $stats['active_subscriptions']; ?></div>
                    <div class="stat-sub"><?php echo $stats['suspended_teams']; ?> suspended</div>
                </div>
            </div>

            <!-- Subscription Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h2>Subscription Overview</h2>
                    <span>Current status</span>
                </div>
                <div class="chart-grid">
                    <div class="chart-item active">
                        <div class="chart-number"><?php echo $subscription_summary['active']; ?></div>
                        <div class="chart-label">Active</div>
                    </div>
                    <div class="chart-item trial">
                        <div class="chart-number"><?php echo $subscription_summary['trial']; ?></div>
                        <div class="chart-label">Trial</div>
                    </div>
                    <div class="chart-item expired">
                        <div class="chart-number"><?php echo $subscription_summary['expired']; ?></div>
                        <div class="chart-label">Expired</div>
                    </div>
                    <div class="chart-item suspended">
                        <div class="chart-number"><?php echo $subscription_summary['suspended']; ?></div>
                        <div class="chart-label">Suspended</div>
                    </div>
                </div>
            </div>

            <!-- Announcement Section (Simplified on Dashboard) -->
            <div class="announcement-section">
                <div class="section-header">
                    <h2>Quick Announcement</h2>
                    <a href="admin_announcements.php" style="color: #1a1a1a;">View All →</a>
                </div>
                
                <form method="POST" class="announcement-form">
                    <input type="hidden" name="action" value="send_announcement">
                    
                    <div class="form-group">
                        <input type="text" name="title" class="form-input" required placeholder="Announcement title">
                    </div>
                    
                    <div class="form-group">
                        <textarea name="message" class="form-textarea" required placeholder="Enter your announcement message..."></textarea>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 12px;">
                        <select name="target" class="form-select" style="flex: 1;">
                            <option value="all">All Users</option>
                            <option value="leaders">Team Leaders Only</option>
                            <option value="members">Members Only</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="team_<?php echo $team['id']; ?>">Team: <?php echo htmlspecialchars($team['team_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            </div>

            <!-- Teams Management -->
            <div class="teams-section">
                <div class="section-header">
                    <h2>Teams Overview</h2>
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterTeams('all')">All</button>
                        <button class="filter-tab" onclick="filterTeams('trial')">Trial</button>
                        <button class="filter-tab" onclick="filterTeams('active')">Active</button>
                        <button class="filter-tab" onclick="filterTeams('expired')">Expired</button>
                        <button class="filter-tab" onclick="filterTeams('suspended')">Suspended</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="teams-table">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Members</th>
                                <th>Leaders</th>
                                <th>Created</th>
                                <th>Trial Ends</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="teamsTableBody">
                            <?php foreach ($teams as $team): ?>
                                <?php
                                $trial_end = new DateTime($team['trial_end_date']);
                                $now = new DateTime();
                                $days_left = $now->diff($trial_end)->days;
                                $is_expired = $trial_end < $now;
                                ?>
                                <tr data-status="<?php echo $team['subscription_status']; ?>">
                                    <td>
                                        <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        <div class="team-email"><?php echo htmlspecialchars($team['email']); ?></div>
                                    </td>
                                    <td><?php echo $team['regular_members'] ?? 0; ?></td>
                                    <td><?php echo $team['leader_count'] ?? 0; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($team['created_at'])); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($team['trial_end_date'])); ?>
                                        <?php if ($team['subscription_status'] == 'trial'): ?>
                                            <div style="font-size: 11px; color: <?php echo $days_left < 7 ? '#dc2626' : '#666'; ?>">
                                                <?php echo $days_left; ?> days left
                                                <button class="btn-extend" onclick="extendTrial(<?php echo $team['id']; ?>)">Extend</button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $team['subscription_status']; ?>">
                                            <?php echo ucfirst($team['subscription_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 4px; flex-wrap: wrap;">
                                            <input type="hidden" name="action" value="update_team_status">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <select name="status" class="status-select">
                                                <option value="trial" <?php echo $team['subscription_status'] == 'trial' ? 'selected' : ''; ?>>Trial</option>
                                                <option value="active" <?php echo $team['subscription_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="expired" <?php echo $team['subscription_status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                                <option value="suspended" <?php echo $team['subscription_status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                            <button type="submit" class="btn-update">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Platform Activity -->
            <div class="teams-section">
                <div class="section-header">
                    <h2>Recent Platform Activity</h2>
                </div>
                
                <?php if (empty($recent_activities)): ?>
                    <div class="empty-state">
                        <span>📊</span>
                        <p>No recent activity</p>
                    </div>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">📊</div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <strong><?php echo htmlspecialchars($activity['name']); ?></strong>
                                        <span class="team-tag"><?php echo htmlspecialchars($activity['team_name']); ?></span>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($activity['action']); ?> • 
                                        <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Extend Trial Modal -->
    <div id="extendTrialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Extend Trial Period</h3>
                <button class="modal-close" onclick="closeModal('extendTrialModal')">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="extend_trial">
                <input type="hidden" name="team_id" id="extend_team_id">
                
                <div class="form-group">
                    <label class="form-label">Extend by (days)</label>
                    <select name="days" class="form-select">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('extendTrialModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Extend Trial</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterTeams(status) {
            // Update active filter
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter table rows
            const rows = document.querySelectorAll('#teamsTableBody tr');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function extendTrial(teamId) {
            document.getElementById('extend_team_id').value = teamId;
            openModal('extendTrialModal');
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

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