<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Dashboard";

// Get dashboard statistics
$stats = [];

// Total members
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE team_id = ? AND role = 'member'");
$stmt->execute([$current_user['team_id']]);
$stats['total_members'] = $stmt->fetchColumn();

// Active members
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE team_id = ? AND role = 'member' AND status = 'active'");
$stmt->execute([$current_user['team_id']]);
$stats['active_members'] = $stmt->fetchColumn();

// New members this month
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE team_id = ? AND role = 'member' AND MONTH(created_at) = MONTH(CURDATE())");
$stmt->execute([$current_user['team_id']]);
$stats['new_members'] = $stmt->fetchColumn();

// Prospects
$stmt = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE team_id = ?");
$stmt->execute([$current_user['team_id']]);
$stats['prospects'] = $stmt->fetchColumn();

// Sales volume
$stmt = $pdo->prepare("SELECT SUM(total_sales) FROM member_profiles WHERE team_id = ?");
$stmt->execute([$current_user['team_id']]);
$stats['sales_volume'] = $stmt->fetchColumn() ?: 0;

// Get team name for invite link
$stmt = $pdo->prepare("SELECT team_name FROM teams WHERE id = ?");
$stmt->execute([$current_user['team_id']]);
$team = $stmt->fetch();
$team_name = $team ? $team['team_name'] : '';

// Recent activities
$stmt = $pdo->prepare("SELECT al.*, u.name as user_name FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE al.team_id = ? ORDER BY al.created_at DESC LIMIT 5");
$stmt->execute([$current_user['team_id']]);
$recent_activities = $stmt->fetchAll();

// Upcoming events
$stmt = $pdo->prepare("SELECT * FROM events WHERE team_id = ? AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$stmt->execute([$current_user['team_id']]);
$upcoming_events = $stmt->fetchAll();

// Pending tasks
$stmt = $pdo->prepare("SELECT t.*, u.name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.team_id = ? AND t.status = 'pending' ORDER BY t.due_date ASC LIMIT 5");
$stmt->execute([$current_user['team_id']]);
$pending_tasks = $stmt->fetchAll();

// Leaderboard preview
$stmt = $pdo->prepare("SELECT u.name, mp.activity_score FROM member_profiles mp JOIN users u ON mp.user_id = u.id WHERE mp.team_id = ? ORDER BY mp.activity_score DESC LIMIT 5");
$stmt->execute([$current_user['team_id']]);
$top_performers = $stmt->fetchAll();

// Get welcome parameter
$show_welcome = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LeaderDesk</title>
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

        .app {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #eaeaea;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            padding: 32px 24px;
        }

        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
        }

        .sidebar-logo span {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 40px;
            margin-left: 8px;
            font-size: 14px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 8px;
            position: relative;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #4a4a4a;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            gap: 12px;
            font-weight: 500;
        }

        .nav-item a:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        .nav-item.active a {
            background: #1a1a1a;
            color: white;
        }

        .nav-icon {
            font-size: 20px;
        }

        .nav-badge {
            background: #dc2626;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 50%;
            margin-left: auto;
            min-width: 20px;
            text-align: center;
            font-weight: 600;
        }

        .main {
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
            font-size: 15px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .notifications {
            position: relative;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc2626;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 50%;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 100px;
            background: #f5f5f5;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-info {
            display: none;
        }

        @media (min-width: 768px) {
            .user-info {
                display: block;
            }
            
            .user-name {
                font-weight: 600;
                font-size: 14px;
            }
            
            .user-role {
                font-size: 12px;
                color: #666;
            }
        }

        .welcome-banner {
            background: #1a1a1a;
            color: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.5s ease-out;
        }

        .welcome-content h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-content p {
            opacity: 0.8;
            font-size: 16px;
        }

        .welcome-close {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.2s;
        }

        .welcome-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .invite-link-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
        }

        .invite-link-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .invite-link-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .invite-link-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .invite-link-box input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
            background: #f5f5f5;
            font-family: 'Inter', sans-serif;
        }

        .invite-link-box button {
            padding: 12px 24px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
            position: relative;
        }

        .invite-link-box button:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .invite-link-box button.copied {
            background: #10b981;
        }

        .copy-message {
            margin-top: 10px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            animation: fadeIn 0.3s ease-out;
            display: none;
        }

        .copy-message.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            display: block;
        }

        .copy-message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            display: block;
        }

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
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .dashboard-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .card-header a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .card-header a:hover {
            color: #1a1a1a;
        }

        .card-content {
            padding: 24px;
        }

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

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
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
            display: flex;
            gap: 16px;
        }

        .activity-points {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .task-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .task-check {
            width: 24px;
            height: 24px;
            border: 2px solid #eaeaea;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .task-check:hover {
            border-color: #1a1a1a;
        }

        .task-check.completed {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .task-meta {
            font-size: 13px;
            color: #888;
            display: flex;
            gap: 16px;
        }

        .task-priority {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 100px;
            background: #f0f0f0;
        }

        .task-priority.high {
            background: #fee2e2;
            color: #991b1b;
        }

        .event-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .event-date {
            min-width: 60px;
            text-align: center;
        }

        .event-date-day {
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
        }

        .event-date-month {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
        }

        .event-content {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .event-meta {
            font-size: 13px;
            color: #888;
            display: flex;
            gap: 16px;
        }

        .event-time {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .leaderboard-rank {
            width: 30px;
            font-weight: 700;
            color: #888;
        }

        .leaderboard-rank.top-1 {
            color: #fbbf24;
        }

        .leaderboard-rank.top-2 {
            color: #94a3b8;
        }

        .leaderboard-rank.top-3 {
            color: #b45309;
        }

        .leaderboard-info {
            flex: 1;
        }

        .leaderboard-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .leaderboard-score {
            font-size: 12px;
            color: #888;
        }

        .leaderboard-points {
            font-weight: 700;
            color: #1a1a1a;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 24px;
        }

        .quick-action {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #1a1a1a;
            transition: all 0.2s;
        }

        .quick-action:hover {
            background: #eaeaea;
            transform: translateY(-2px);
        }

        .quick-action span {
            display: block;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .quick-action small {
            font-size: 13px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 15px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .invite-link-box {
                flex-direction: column;
                align-items: stretch;
            }
            
            .invite-link-box button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                LeaderDesk<span>.co</span>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="dashboard.php">
                        <span class="nav-icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="team.php">
                        <span class="nav-icon">👥</span>
                        Team
                    </a>
                </li>
                <li class="nav-item">
                    <a href="prospects.php">
                        <span class="nav-icon">🎯</span>
                        Prospects
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tasks.php">
                        <span class="nav-icon">✅</span>
                        Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a href="targets.php">
                        <span class="nav-icon">🎯</span>
                        Targets
                    </a>
                </li>
                <li class="nav-item">
                    <a href="training.php">
                        <span class="nav-icon">📚</span>
                        Training
                    </a>
                </li>
                <li class="nav-item">
                    <a href="events.php">
                        <span class="nav-icon">📅</span>
                        Events
                    </a>
                </li>
                <li class="nav-item">
                    <a href="leaderboard.php">
                        <span class="nav-icon">🏆</span>
                        Leaderboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php">
                        <span class="nav-icon">🔔</span>
                        Notifications
                        <span class="nav-badge" id="sideNotificationCount" style="display: none;"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php">
                        <span class="nav-icon">👤</span>
                        Profile
                    </a>
                </li>
                
                <?php if (isset($current_user) && $current_user['role'] == 'super_admin'): ?>
                    <li class="nav-item" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
                        <a href="admin_dashboard.php">
                            <span class="nav-icon">⚡</span>
                            Admin Panel
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
                    <a href="logout.php" style="color: #ef4444;">
                        <span class="nav-icon">🚪</span>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($current_user['name']); ?> 👋</p>
                </div>
                
                <div class="user-menu">
                    <div class="notifications">
                        <a href="notifications.php" style="text-decoration: none; color: inherit;">
                            <span>🔔</span>
                            <span class="notification-badge" id="headerNotificationCount" style="display: none;">0</span>
                        </a>
                    </div>
                    
                    <a href="profile.php" style="text-decoration: none; color: inherit;">
                        <div class="user-profile">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                                <div class="user-role"><?php echo ucfirst($current_user['role']); ?></div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Welcome Banner -->
            <?php if ($show_welcome): ?>
            <div class="welcome-banner" id="welcomeBanner">
                <div class="welcome-content">
                    <h2>🎉 Welcome to LeaderDesk, <?php echo htmlspecialchars($current_user['name']); ?>!</h2>
                    <p>Your 2-month free trial has started. Here's how to get the most out of it...</p>
                </div>
                <button class="welcome-close" onclick="document.getElementById('welcomeBanner').remove()">×</button>
            </div>
            <?php endif; ?>

            <!-- Invite Link Section (only for team leaders) -->
            <?php if ($current_user['role'] == 'team_leader'): ?>
            <div class="invite-link-card">
                <h3>🔗 Invite Members to Your Team</h3>
                <p>Share this link with people you want to join your team. When they register, they'll be automatically added.</p>
                <div class="invite-link-box">
                    <input type="text" id="inviteLink" value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/leaderdesk/join.php?team=' . urlencode($team_name); ?>" readonly>
                    <button onclick="copyInviteLink()" id="copyButton">Copy Link</button>
                </div>
                <div id="copyMessage" class="copy-message"></div>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value"><?php echo $stats['total_members']; ?></div>
                    <div class="stat-trend trend-up">↑ 12% from last month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Active Members</div>
                    <div class="stat-value"><?php echo $stats['active_members']; ?></div>
                    <div class="stat-trend trend-up">↑ 8% from last month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Prospects</div>
                    <div class="stat-value"><?php echo $stats['prospects']; ?></div>
                    <div class="stat-trend trend-up">↑ 15% from last month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Sales Volume</div>
                    <div class="stat-value">₦<?php echo number_format($stats['sales_volume']); ?></div>
                    <div class="stat-trend trend-up">↑ 10% from last month</div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column -->
                <div class="left-column">
                    <!-- Recent Activity -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Recent Activity</h3>
                            <a href="#">View all</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recent_activities)): ?>
                                <div class="empty-state">
                                    <span>📊</span>
                                    <p>No activity yet. Start by adding team members or prospects.</p>
                                </div>
                            <?php else: ?>
                                <ul class="activity-list">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <li class="activity-item">
                                            <div class="activity-icon">
                                                <?php
                                                if (strpos($activity['action'], 'task') !== false) echo '✅';
                                                elseif (strpos($activity['action'], 'prospect') !== false) echo '👤';
                                                elseif (strpos($activity['action'], 'event') !== false) echo '📅';
                                                else echo '📝';
                                                ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                <div class="activity-meta">
                                                    <span><?php echo time_elapsed_string($activity['created_at']); ?></span>
                                                    <span class="activity-points">+<?php echo $activity['points_earned']; ?> pts</span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pending Tasks -->
                    <div class="dashboard-card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3>Pending Tasks</h3>
                            <a href="tasks.php">View all</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($pending_tasks)): ?>
                                <div class="empty-state">
                                    <span>✅</span>
                                    <p>No pending tasks. Great job!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pending_tasks as $task): ?>
                                    <div class="task-item">
                                        <div class="task-check"></div>
                                        <div class="task-content">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="task-meta">
                                                <span>Due <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                                <?php if ($task['assigned_to_name']): ?>
                                                    <span>Assigned to <?php echo htmlspecialchars($task['assigned_to_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="task-priority <?php echo strtotime($task['due_date']) < time() ? 'high' : ''; ?>">
                                            <?php echo strtotime($task['due_date']) < time() ? 'Overdue' : 'Pending'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-column">
                    <!-- Upcoming Events -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Upcoming Events</h3>
                            <a href="events.php">View all</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($upcoming_events)): ?>
                                <div class="empty-state">
                                    <span>📅</span>
                                    <p>No upcoming events. Schedule your first event.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="event-item">
                                        <div class="event-date">
                                            <div class="event-date-day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                            <div class="event-date-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                        </div>
                                        <div class="event-content">
                                            <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                            <div class="event-meta">
                                                <span class="event-time">
                                                    <span>⏰</span>
                                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                                </span>
                                                <span><?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Leaderboard Preview -->
                    <div class="dashboard-card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3>Top Performers</h3>
                            <a href="leaderboard.php">View all</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($top_performers)): ?>
                                <div class="empty-state">
                                    <span>🏆</span>
                                    <p>No data yet. Start adding team members.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($top_performers as $index => $performer): ?>
                                    <div class="leaderboard-item">
                                        <div class="leaderboard-rank <?php echo $index < 3 ? 'top-' . ($index + 1) : ''; ?>">
                                            #<?php echo $index + 1; ?>
                                        </div>
                                        <div class="leaderboard-info">
                                            <div class="leaderboard-name"><?php echo htmlspecialchars($performer['name']); ?></div>
                                            <div class="leaderboard-score">Activity Score</div>
                                        </div>
                                        <div class="leaderboard-points"><?php echo $performer['activity_score']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <a href="team.php" class="quick-action">
                            <span>👤</span>
                            <small>Add Member</small>
                        </a>
                        <a href="prospects.php" class="quick-action">
                            <span>🎯</span>
                            <small>Add Prospect</small>
                        </a>
                        <a href="tasks.php" class="quick-action">
                            <span>✅</span>
                            <small>Create Task</small>
                        </a>
                        <a href="events.php" class="quick-action">
                            <span>📅</span>
                            <small>Schedule Event</small>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function time_elapsed_string(datetime) {
            const now = new Date();
            const past = new Date(datetime);
            const diff = Math.floor((now - past) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) {
                const minutes = Math.floor(diff / 60);
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            }
            if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            }
            const days = Math.floor(diff / 86400);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        }

        function copyInviteLink() {
            const copyText = document.getElementById("inviteLink");
            const copyButton = document.getElementById("copyButton");
            const copyMessage = document.getElementById("copyMessage");
            
            // Select the text
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            
            // Try using the modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(copyText.value).then(function() {
                    // Success
                    copyButton.classList.add('copied');
                    copyButton.textContent = 'Copied!';
                    copyMessage.className = 'copy-message success';
                    copyMessage.innerHTML = '✅ Link copied to clipboard!';
                    
                    setTimeout(function() {
                        copyButton.classList.remove('copied');
                        copyButton.textContent = 'Copy Link';
                        copyMessage.className = 'copy-message';
                        copyMessage.innerHTML = '';
                    }, 3000);
                }).catch(function(err) {
                    // Fallback to execCommand
                    fallbackCopy();
                });
            } else {
                // Fallback to execCommand
                fallbackCopy();
            }
            
            function fallbackCopy() {
                try {
                    var successful = document.execCommand('copy');
                    
                    if (successful) {
                        copyButton.classList.add('copied');
                        copyButton.textContent = 'Copied!';
                        copyMessage.className = 'copy-message success';
                        copyMessage.innerHTML = '✅ Link copied to clipboard!';
                        
                        setTimeout(function() {
                            copyButton.classList.remove('copied');
                            copyButton.textContent = 'Copy Link';
                            copyMessage.className = 'copy-message';
                            copyMessage.innerHTML = '';
                        }, 3000);
                    } else {
                        copyMessage.className = 'copy-message error';
                        copyMessage.innerHTML = '❌ Copy failed. Please copy manually.';
                        
                        setTimeout(function() {
                            copyMessage.className = 'copy-message';
                            copyMessage.innerHTML = '';
                        }, 3000);
                    }
                } catch (err) {
                    copyMessage.className = 'copy-message error';
                    copyMessage.innerHTML = '❌ Copy failed. Please copy manually.';
                    
                    setTimeout(function() {
                        copyMessage.className = 'copy-message';
                        copyMessage.innerHTML = '';
                    }, 3000);
                }
            }
        }

        function updateNotificationBadges() {
            fetch('ajax/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const sideBadge = document.getElementById('sideNotificationCount');
                    if (sideBadge) {
                        if (data.unread_count > 0) {
                            sideBadge.textContent = data.unread_count;
                            sideBadge.style.display = 'inline';
                        } else {
                            sideBadge.textContent = '';
                            sideBadge.style.display = 'none';
                        }
                    }
                    
                    const headerBadge = document.getElementById('headerNotificationCount');
                    if (headerBadge) {
                        if (data.unread_count > 0) {
                            headerBadge.textContent = data.unread_count;
                            headerBadge.style.display = 'inline';
                        } else {
                            headerBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error updating badges:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
        });
    </script>
</body>
</html>