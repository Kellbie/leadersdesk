<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "My Profile";
$message = '';
$error = '';

// Get user ID from query string (for viewing other profiles) or use current user
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $current_user['id'];

// Check if user belongs to same team
$stmt = $pdo->prepare("SELECT team_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$target_user = $stmt->fetch();

if (!$target_user || $target_user['team_id'] != $current_user['team_id']) {
    header("Location: dashboard.php");
    exit();
}

// Get user profile data
$stmt = $pdo->prepare("
    SELECT u.*, 
           mp.rank, 
           mp.member_type, 
           mp.activity_score, 
           mp.total_recruits, 
           mp.total_sales, 
           mp.join_date,
           mp.upline_user_id,
           u2.name as upline_name,
           t.team_name, 
           t.country, 
           t.state_province,
           tb.tagline,
           tb.primary_color
    FROM users u
    LEFT JOIN member_profiles mp ON u.id = mp.user_id
    LEFT JOIN users u2 ON mp.upline_user_id = u2.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN team_branding tb ON u.team_id = tb.team_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

// Get user badges
$stmt = $pdo->prepare("
    SELECT b.* FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
");
$stmt->execute([$user_id]);
$badges = $stmt->fetchAll();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$activities = $stmt->fetchAll();

// Get upcoming events user is attending
$stmt = $pdo->prepare("
    SELECT e.*, ea.response 
    FROM events e
    JOIN event_attendance ea ON e.id = ea.event_id
    WHERE ea.user_id = ? AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcoming_events = $stmt->fetchAll();

// Get pending tasks assigned to user
$stmt = $pdo->prepare("
    SELECT * FROM tasks 
    WHERE assigned_to = ? AND status = 'pending'
    ORDER BY due_date ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$pending_tasks = $stmt->fetchAll();

// Get downline count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM member_profiles 
    WHERE upline_user_id = ?
");
$stmt->execute([$user_id]);
$downline_count = $stmt->fetchColumn();

// Handle profile update (only for own profile)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user_id == $current_user['id']) {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required';
        } else {
            try {
                // Check if email is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'Email already in use by another member';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $email, $user_id]);
                    
                    // Create notification
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type) VALUES (?, ?, 'Profile Updated', 'Your profile has been updated successfully', 'system')");
                    $stmt->execute([$current_user['team_id'], $user_id]);
                    
                    $_SESSION['success_message'] = "Profile updated successfully!";
                    header("Location: profile.php");
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Failed to update profile';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $_SESSION['success_message'] = "Password changed successfully!";
            header("Location: profile.php");
            exit();
        }
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$is_own_profile = ($user_id == $current_user['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_own_profile ? 'My Profile' : htmlspecialchars($profile['name']) . "'s Profile"; ?> - LeaderDesk</title>
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

        .back-button {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back-button:hover {
            color: #1a1a1a;
        }

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

        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .profile-header {
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 32px;
            flex-wrap: wrap;
            animation: fadeIn 0.3s ease-out;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 600;
            border: 4px solid <?php echo $profile['primary_color'] ?? '#1a1a1a'; ?>;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .profile-badges {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .profile-badge {
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge-rank {
            background: #1a1a1a;
            color: white;
        }

        .badge-type {
            background: #f0f0f0;
            color: #666;
        }

        .profile-meta {
            display: flex;
            gap: 24px;
            color: #666;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 8px;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            font-size: 15px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        .tab-btn.active {
            background: #1a1a1a;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 32px;
            animation: fadeIn 0.3s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        .tab-content h2 {
            font-size: 20px;
            margin-bottom: 24px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .info-group {
            margin-bottom: 20px;
        }

        .info-label {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 500;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .badge-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #fafafa;
            border-radius: 16px;
            transition: all 0.2s;
        }

        .badge-card:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .badge-icon-large {
            font-size: 32px;
        }

        .badge-info h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .badge-info p {
            font-size: 12px;
            color: #666;
        }

        .activity-timeline {
            position: relative;
            padding-left: 32px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 24px;
            border-left: 2px solid #eaeaea;
            padding-left: 24px;
        }

        .timeline-item:last-child {
            border-left-color: transparent;
        }

        .timeline-icon {
            position: absolute;
            left: -17px;
            width: 32px;
            height: 32px;
            background: white;
            border: 2px solid #1a1a1a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .timeline-content {
            background: #fafafa;
            padding: 16px;
            border-radius: 12px;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .timeline-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .timeline-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
        }

        .points-badge {
            background: #1a1a1a;
            color: white;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 11px;
        }

        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .task-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
            border-left: 4px solid;
        }

        .task-item.pending {
            border-left-color: #f59e0b;
        }

        .task-item.completed {
            border-left-color: #10b981;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .task-meta {
            font-size: 12px;
            color: #888;
        }

        .task-points {
            font-weight: 600;
            color: #1a1a1a;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .event-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
        }

        .event-date {
            min-width: 50px;
            text-align: center;
        }

        .event-date .day {
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }

        .event-date .month {
            font-size: 11px;
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
            font-size: 12px;
            color: #888;
        }

        .event-response {
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .response-attending {
            background: #ecfdf5;
            color: #065f46;
        }

        .profile-form {
            max-width: 500px;
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
            padding: 12px 16px;
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

        .form-input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
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

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .logout-card {
            margin-top: 40px;
            padding: 24px;
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .danger-zone {
            background: #fef2f2;
            border-radius: 12px;
            border: 1px solid #fee2e2;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .danger-zone h4 {
            font-size: 16px;
            color: #991b1b;
            margin-bottom: 4px;
        }

        .danger-zone p {
            color: #b91c1c;
            font-size: 14px;
        }

        .btn-logout {
            background: #ef4444;
            color: white;
            padding: 12px 24px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-logout:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.3);
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
                transform: translateY(-10px);
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

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .badges-grid {
                grid-template-columns: 1fr;
            }

            .profile-meta {
                justify-content: center;
            }

            .danger-zone {
                flex-direction: column;
                text-align: center;
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
                <li class="nav-item">
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
                <li class="nav-item active">
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
                    <h1><?php echo $is_own_profile ? 'My Profile' : htmlspecialchars($profile['name']) . "'s Profile"; ?></h1>
                    <p><?php echo $is_own_profile ? 'Manage your personal information and view your activity' : 'View team member profile and activity'; ?></p>
                </div>
                
                <?php if (!$is_own_profile): ?>
                    <a href="team.php" class="back-button">
                        <span>←</span> Back to Team
                    </a>
                <?php endif; ?>
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

            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
                    </div>
                    
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($profile['name']); ?></h1>
                        
                        <div class="profile-badges">
                            <span class="profile-badge badge-rank"><?php echo htmlspecialchars($profile['rank'] ?? 'Member'); ?></span>
                            <span class="profile-badge badge-type"><?php echo ucfirst($profile['member_type'] ?? 'Member'); ?></span>
                            <?php if ($profile['status'] == 'active'): ?>
                                <span class="profile-badge" style="background: #ecfdf5; color: #065f46;">Active</span>
                            <?php else: ?>
                                <span class="profile-badge" style="background: #fef2f2; color: #991b1b;">Inactive</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-meta">
                            <div class="meta-item">
                                <span>📧</span>
                                <span><?php echo htmlspecialchars($profile['email']); ?></span>
                            </div>
                            <?php if ($profile['phone']): ?>
                                <div class="meta-item">
                                    <span>📞</span>
                                    <span><?php echo htmlspecialchars($profile['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <span>📅</span>
                                <span>Joined <?php echo date('F Y', strtotime($profile['join_date'] ?? $profile['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $profile['activity_score'] ?? 0; ?></div>
                        <div class="stat-label">Activity Score</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $profile['total_recruits'] ?? 0; ?></div>
                        <div class="stat-label">Total Recruits</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">₦<?php echo number_format($profile['total_sales'] ?? 0); ?></div>
                        <div class="stat-label">Total Sales</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $downline_count; ?></div>
                        <div class="stat-label">Downline</div>
                    </div>
                </div>

                <!-- Profile Tabs -->
                <div class="profile-tabs">
                    <button class="tab-btn active" onclick="showTab('about')">About</button>
                    <button class="tab-btn" onclick="showTab('badges')">Badges (<?php echo count($badges); ?>)</button>
                    <button class="tab-btn" onclick="showTab('activity')">Activity</button>
                    <button class="tab-btn" onclick="showTab('tasks')">Tasks</button>
                    <button class="tab-btn" onclick="showTab('events')">Events</button>
                    <?php if ($is_own_profile): ?>
                        <button class="tab-btn" onclick="showTab('settings')">Settings</button>
                    <?php endif; ?>
                </div>

                <!-- About Tab -->
                <div id="tab-about" class="tab-content active">
                    <h2>About</h2>
                    <div class="info-grid">
                        <div>
                            <div class="info-group">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($profile['name']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($profile['email']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($profile['phone'] ?: 'Not provided'); ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="info-group">
                                <div class="info-label">Team</div>
                                <div class="info-value"><?php echo htmlspecialchars($profile['team_name']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Location</div>
                                <div class="info-value"><?php echo htmlspecialchars($profile['country'] ?: 'Not specified'); ?><?php echo $profile['state_province'] ? ', ' . htmlspecialchars($profile['state_province']) : ''; ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Upline / Sponsor</div>
                                <div class="info-value"><?php echo $profile['upline_name'] ? htmlspecialchars($profile['upline_name']) : 'Top Leader'; ?></div>
                            </div>
                            
                            <?php if ($profile['tagline']): ?>
                                <div class="info-group">
                                    <div class="info-label">Team Tagline</div>
                                    <div class="info-value"><?php echo htmlspecialchars($profile['tagline']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Badges Tab -->
                <div id="tab-badges" class="tab-content">
                    <h2>Earned Badges</h2>
                    <?php if (empty($badges)): ?>
                        <div class="empty-state">
                            <span>🏅</span>
                            <p>No badges earned yet. Complete activities to earn badges!</p>
                        </div>
                    <?php else: ?>
                        <div class="badges-grid">
                            <?php foreach ($badges as $badge): ?>
                                <div class="badge-card">
                                    <div class="badge-icon-large"><?php echo $badge['icon']; ?></div>
                                    <div class="badge-info">
                                        <h4><?php echo htmlspecialchars($badge['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($badge['description']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Tab -->
                <div id="tab-activity" class="tab-content">
                    <h2>Recent Activity</h2>
                    <?php if (empty($activities)): ?>
                        <div class="empty-state">
                            <span>📊</span>
                            <p>No activity yet. Start taking actions to see your activity feed!</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <?php
                                        if (strpos($activity['action'], 'task') !== false) echo '✅';
                                        elseif (strpos($activity['action'], 'prospect') !== false) echo '👤';
                                        elseif (strpos($activity['action'], 'event') !== false) echo '📅';
                                        elseif (strpos($activity['action'], 'training') !== false) echo '📚';
                                        else echo '📝';
                                        ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div class="timeline-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        <div class="timeline-meta">
                                            <span><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                            <span class="points-badge">+<?php echo $activity['points_earned']; ?> pts</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tasks Tab -->
                <div id="tab-tasks" class="tab-content">
                    <h2>Pending Tasks</h2>
                    <?php if (empty($pending_tasks)): ?>
                        <div class="empty-state">
                            <span>✅</span>
                            <p>No pending tasks. Great job!</p>
                        </div>
                    <?php else: ?>
                        <div class="tasks-list">
                            <?php foreach ($pending_tasks as $task): ?>
                                <div class="task-item pending">
                                    <div class="task-content">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-meta">Due <?php echo date('M d, Y', strtotime($task['due_date'])); ?></div>
                                    </div>
                                    <div class="task-points"><?php echo $task['points']; ?> pts</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Events Tab -->
                <div id="tab-events" class="tab-content">
                    <h2>Upcoming Events</h2>
                    <?php if (empty($upcoming_events)): ?>
                        <div class="empty-state">
                            <span>📅</span>
                            <p>No upcoming events</p>
                        </div>
                    <?php else: ?>
                        <div class="events-list">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-item">
                                    <div class="event-date">
                                        <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                        <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                    </div>
                                    <div class="event-content">
                                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div class="event-meta"><?php echo date('g:i A', strtotime($event['event_time'])); ?></div>
                                    </div>
                                    <span class="event-response response-<?php echo $event['response']; ?>">
                                        <?php echo ucfirst($event['response']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Settings Tab (Only for own profile) -->
                <?php if ($is_own_profile): ?>
                    <div id="tab-settings" class="tab-content">
                        <h2>Profile Settings</h2>
                        
                        <form method="POST" action="" class="profile-form" style="margin-bottom: 40px;">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <h3 style="font-size: 16px; margin-bottom: 16px;">Personal Information</h3>
                            
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                        
                        <form method="POST" action="" style="margin-bottom: 40px;">
                            <input type="hidden" name="action" value="change_password">
                            
                            <h3 style="font-size: 16px; margin-bottom: 16px;">Change Password</h3>
                            
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>

                        <!-- Logout Card in Settings Tab -->
                        <div class="logout-card">
                            <h3 style="font-size: 16px; margin-bottom: 16px; color: #ef4444;">Session</h3>
                            <div class="danger-zone">
                                <div>
                                    <h4>Logout of your account</h4>
                                    <p>End your current session securely</p>
                                </div>
                                <a href="logout.php" class="btn-logout">
                                    <span>🚪</span>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(`tab-${tabName}`).classList.add('active');
            event.target.classList.add('active');
        }

        // Update notification badges
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
                            sideBadge.style.display = 'none';
                        }
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
        });

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>