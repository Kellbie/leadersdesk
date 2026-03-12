<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Team";
$message = '';
$error = '';

// Handle adding team member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_member' && $current_user['role'] == 'team_leader') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $member_type = $_POST['member_type'] ?? 'member';
    $rank = $_POST['rank'] ?? 'Member';
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            // Generate random password
            $temp_password = bin2hex(random_bytes(4));
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Create user account
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, team_id, status) VALUES (?, ?, ?, ?, 'member', ?, 'active')");
            $stmt->execute([$name, $email, $phone, $hashed_password, $current_user['team_id']]);
            $user_id = $pdo->lastInsertId();
            
            // Create member profile with selected rank
            $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, ?, ?, CURDATE())");
            $stmt->execute([$user_id, $current_user['team_id'], $rank, $member_type]);
            
            // Create notification for new member
            $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type) VALUES (?, ?, 'Welcome to the team!', ?, 'member')");
            $stmt->execute([$current_user['team_id'], $user_id, "You've been added to the team by " . $current_user['name']]);
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'add_member', ?, 10)");
            $stmt->execute([$current_user['team_id'], $current_user['id'], "Added team member: $name"]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Member added successfully! Their temporary password is: $temp_password";
            header("Location: team.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to add member: ' . $e->getMessage();
        }
    }
}

// Handle status update (only for team leaders)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status' && $current_user['role'] == 'team_leader') {
    $user_id = $_POST['user_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($user_id && $status) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND team_id = ?");
        $stmt->execute([$status, $user_id, $current_user['team_id']]);
        $_SESSION['success_message'] = "Member status updated";
        header("Location: team.php");
        exit();
    }
}

// Handle upgrade approval/rejection (only for team leaders)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $current_user['role'] == 'team_leader') {
    if ($_POST['action'] == 'approve_upgrade') {
        $user_id = $_POST['user_id'] ?? 0;
        
        // Update user role to team_leader
        $stmt = $pdo->prepare("UPDATE users SET role = 'team_leader', upgrade_requested = 0 WHERE id = ? AND team_id = ?");
        $stmt->execute([$user_id, $current_user['team_id']]);
        
        // Update member profile rank
        $stmt = $pdo->prepare("UPDATE member_profiles SET rank = 'Leader' WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Notify the user
        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'Upgrade Approved', ?, 'system', NOW())");
        $stmt->execute([$current_user['team_id'], $user_id, "Congratulations! You have been upgraded to Team Leader."]);
        
        $_SESSION['success_message'] = "Member upgraded to Team Leader successfully!";
        header("Location: team.php");
        exit();
        
    } elseif ($_POST['action'] == 'reject_upgrade') {
        $user_id = $_POST['user_id'] ?? 0;
        
        // Reset upgrade request
        $stmt = $pdo->prepare("UPDATE users SET upgrade_requested = 0 WHERE id = ? AND team_id = ?");
        $stmt->execute([$user_id, $current_user['team_id']]);
        
        // Notify the user
        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'Upgrade Request Declined', ?, 'system', NOW())");
        $stmt->execute([$current_user['team_id'], $user_id, "Your request to become a Team Leader has been declined."]);
        
        $_SESSION['success_message'] = "Upgrade request rejected.";
        header("Location: team.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get team members with their profiles
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
           (SELECT COUNT(*) FROM member_profiles WHERE upline_user_id = u.id) as downline_count
    FROM users u
    LEFT JOIN member_profiles mp ON u.id = mp.user_id
    LEFT JOIN users u2 ON mp.upline_user_id = u2.id
    WHERE u.team_id = ? AND u.role = 'member'
    ORDER BY u.created_at DESC
");
$stmt->execute([$current_user['team_id']]);
$members = $stmt->fetchAll();

// Get members with upgrade requests (for team leaders)
$upgrade_requests = [];
if ($current_user['role'] == 'team_leader') {
    $stmt = $pdo->prepare("SELECT u.*, mp.rank FROM users u LEFT JOIN member_profiles mp ON u.id = mp.user_id WHERE u.team_id = ? AND u.role = 'member' AND u.upgrade_requested = 1");
    $stmt->execute([$current_user['team_id']]);
    $upgrade_requests = $stmt->fetchAll();
}

// Get team statistics
$total_members = count($members);
$active_members = count(array_filter($members, function($m) { return $m['status'] == 'active'; }));
$total_recruits = array_sum(array_column($members, 'total_recruits'));
$total_sales = array_sum(array_column($members, 'total_sales'));

// Define rank options for dropdown
$ranks = [
    'No Rank',
    'Partner',
    'Senior Manager',
    'Bronze Executive',
    'Silver Executive',
    'Gold Executive'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - LeaderDesk</title>
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

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
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
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .stat-sub {
            font-size: 13px;
            color: #888;
        }

        /* Upgrade Requests Section */
        .upgrade-requests-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
        }

        .upgrade-requests-section h2 {
            font-size: 18px;
            margin-bottom: 16px;
            color: #f59e0b;
        }

        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .request-card {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .request-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .request-info p {
            font-size: 13px;
            color: #92400e;
        }

        .request-actions {
            display: flex;
            gap: 8px;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 20px;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 2;
            min-width: 250px;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .filter-select {
            flex: 1;
            min-width: 150px;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 13px;
            background: white;
        }

        .search-results {
            margin-top: 12px;
            font-size: 13px;
            color: #666;
        }

        /* Teams Table */
        .teams-table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .table-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
        }

        .table-header h2 {
            font-size: 16px;
            font-weight: 600;
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
        }

        .teams-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f5f5f5;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            border-bottom: 1px solid #eaeaea;
        }

        .teams-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eaeaea;
            font-size: 13px;
            vertical-align: middle;
        }

        .teams-table tr:hover {
            background: #fafafa;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .member-avatar {
            width: 36px;
            height: 36px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .member-details {
            display: flex;
            flex-direction: column;
        }

        .member-name {
            font-weight: 600;
            color: #1a1a1a;
        }

        .member-email {
            font-size: 11px;
            color: #666;
        }

        .rank-badge {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-inactive {
            background: #fef2f2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 10px;
            border: 1px solid #eaeaea;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
            text-decoration: none;
            color: #1a1a1a;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-icon:hover {
            background: #f5f5f5;
        }

        .btn-icon.view:hover {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #0369a1;
        }

        .status-select {
            padding: 6px 10px;
            border: 1px solid #eaeaea;
            border-radius: 6px;
            font-size: 11px;
            background: white;
        }

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
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
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
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 24px;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input,
            .filter-select {
                width: 100%;
            }

            .teams-table {
                min-width: 800px;
            }

            .teams-table-container {
                overflow-x: auto;
            }

            .form-row {
                grid-template-columns: 1fr;
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
                <li class="nav-item active">
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
                    <h1>Team Management</h1>
                    <p>Manage your team members and track their performance</p>
                </div>
                
                <div class="header-actions">
                    <?php if ($current_user['role'] == 'team_leader'): ?>
                        <button class="btn btn-primary" onclick="openModal('addMemberModal')">
                            <span>+</span> Add Member
                        </button>
                    <?php endif; ?>
                </div>
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

            <!-- Upgrade Requests Section (only visible to team leaders) -->
            <?php if ($current_user['role'] == 'team_leader' && !empty($upgrade_requests)): ?>
                <div class="upgrade-requests-section">
                    <h2>⬆️ Upgrade Requests</h2>
                    <div class="requests-grid">
                        <?php foreach ($upgrade_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-info">
                                    <h4><?php echo htmlspecialchars($request['name']); ?></h4>
                                    <p>Wants to become a Team Leader</p>
                                </div>
                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_upgrade">
                                        <input type="hidden" name="user_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="padding: 6px 12px; font-size: 12px;">✓ Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject_upgrade">
                                        <input type="hidden" name="user_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">✗ Reject</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-sub">Across your team</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Active Now</div>
                    <div class="stat-value"><?php echo $active_members; ?></div>
                    <div class="stat-sub"><?php echo $total_members ? round(($active_members/$total_members)*100) : 0; ?>% engagement</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Recruits</div>
                    <div class="stat-value"><?php echo $total_recruits; ?></div>
                    <div class="stat-sub">Combined downline</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value">₦<?php echo number_format($total_sales); ?></div>
                    <div class="stat-sub">Team performance</div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name or email..." onkeyup="filterMembers()">
                    
                    <select id="statusFilter" class="filter-select" onchange="filterMembers()">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    
                    <select id="rankFilter" class="filter-select" onchange="filterMembers()">
                        <option value="all">All Ranks</option>
                        <?php foreach ($ranks as $rank): ?>
                            <option value="<?php echo $rank; ?>"><?php echo $rank; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-results" id="searchResults">Showing <?php echo $total_members; ?> members</div>
            </div>

            <!-- Teams Table -->
            <?php if (empty($members)): ?>
                <div class="empty-state">
                    <span>👥</span>
                    <h3>No team members yet</h3>
                    <p>Start building your team by adding your first member</p>
                    <?php if ($current_user['role'] == 'team_leader'): ?>
                        <button class="btn btn-primary" onclick="openModal('addMemberModal')">Add Your First Member</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="teams-table-container">
                    <div class="table-header">
                        <h2>Team Members (<?php echo $total_members; ?>)</h2>
                    </div>
                    
                    <table class="teams-table" id="teamsTable">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Rank</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Recruits</th>
                                <th>Sales</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <?php foreach ($members as $member): ?>
                                <tr data-status="<?php echo $member['status']; ?>" 
                                    data-rank="<?php echo $member['rank'] ?? 'No Rank'; ?>"
                                    data-search="<?php echo strtolower($member['name'] . ' ' . $member['email']); ?>">
                                    
                                    <td>
                                        <div class="member-info">
                                            <div class="member-avatar">
                                                <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                            </div>
                                            <div class="member-details">
                                                <span class="member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                                                <span class="member-email"><?php echo htmlspecialchars($member['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="rank-badge"><?php echo htmlspecialchars($member['rank'] ?? 'Member'); ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge status-<?php echo $member['status']; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td><?php echo $member['activity_score'] ?? 0; ?></td>
                                    <td><?php echo $member['total_recruits'] ?? 0; ?></td>
                                    <td>₦<?php echo number_format($member['total_sales'] ?? 0); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($member['join_date'] ?? $member['created_at'])); ?></td>
                                    
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon view" onclick="viewMember(<?php echo $member['id']; ?>)">👁️</button>
                                            
                                            <?php if ($current_user['role'] == 'team_leader'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                                        <option value="active" <?php echo $member['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $member['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Member Modal (only visible to team leaders) -->
    <?php if ($current_user['role'] == 'team_leader'): ?>
    <div id="addMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Team Member</h2>
                <button class="modal-close" onclick="closeModal('addMemberModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_member">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="Enter member's full name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-input" required placeholder="member@example.com">
                    <small style="color: #888; font-size: 12px; margin-top: 4px; display: block;">A temporary password will be generated</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="+1 (555) 000-0000">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Member Type</label>
                        <select name="member_type" class="form-select">
                            <option value="member">Member</option>
                            <option value="prospect">Prospect</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rank</label>
                        <select name="rank" class="form-select">
                            <?php foreach ($ranks as $rank): ?>
                                <option value="<?php echo $rank; ?>"><?php echo $rank; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addMemberModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function viewMember(id) {
            window.location.href = 'profile.php?user_id=' + id;
        }

        function filterMembers() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rankFilter = document.getElementById('rankFilter').value;
            
            const rows = document.querySelectorAll('#membersTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const status = row.dataset.status;
                const rank = row.dataset.rank;
                const searchText = row.dataset.search;
                
                const statusMatch = statusFilter === 'all' || status === statusFilter;
                const rankMatch = rankFilter === 'all' || rank === rankFilter;
                const searchMatch = searchInput === '' || searchText.includes(searchInput);
                
                if (statusMatch && rankMatch && searchMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('searchResults').textContent = `Showing ${visibleCount} of ${rows.length} members`;
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
                })
                .catch(error => console.error('Error updating badges:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
            filterMembers(); // Initialize filter on page load
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>