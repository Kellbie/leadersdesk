<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Prospects";
$message = '';
$error = '';

// Handle prospect creation/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_prospect') {
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $source = $_POST['source'] ?? '';
        $stage = $_POST['stage'] ?? 'new';
        $follow_up_date = $_POST['follow_up_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if (empty($name)) {
            $error = 'Name is required';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO prospects (team_id, user_id, name, phone, email, source, stage, follow_up_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $name, $phone, $email, $source, $stage, $follow_up_date, $notes]);
                
                // Award points
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'add_prospect', ?, 5)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], "Added prospect: $name"]);
                
                $_SESSION['success_message'] = "Prospect added successfully!";
                header("Location: prospects.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to add prospect';
            }
        }
    } elseif ($_POST['action'] == 'update_stage') {
        $prospect_id = $_POST['prospect_id'] ?? 0;
        $stage = $_POST['stage'] ?? '';
        
        if ($prospect_id && $stage) {
            // Check if user has permission to update this prospect
            if ($current_user['role'] == 'member') {
                $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
                $stmt->execute([$prospect_id, $current_user['team_id'], $current_user['id']]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ?");
                $stmt->execute([$prospect_id, $current_user['team_id']]);
            }
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE prospects SET stage = ? WHERE id = ? AND team_id = ?");
                $stmt->execute([$stage, $prospect_id, $current_user['team_id']]);
                
                $_SESSION['success_message'] = "Stage updated successfully!";
            } else {
                $error = "You don't have permission to update this prospect.";
            }
            header("Location: prospects.php");
            exit();
        }
    } elseif ($_POST['action'] == 'edit_prospect') {
        $prospect_id = $_POST['prospect_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $source = $_POST['source'] ?? '';
        $stage = $_POST['stage'] ?? '';
        $follow_up_date = $_POST['follow_up_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($prospect_id && $name) {
            // Check if user has permission to edit this prospect
            if ($current_user['role'] == 'member') {
                $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
                $stmt->execute([$prospect_id, $current_user['team_id'], $current_user['id']]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ?");
                $stmt->execute([$prospect_id, $current_user['team_id']]);
            }
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE prospects SET name = ?, phone = ?, email = ?, source = ?, stage = ?, follow_up_date = ?, notes = ? WHERE id = ? AND team_id = ?");
                $stmt->execute([$name, $phone, $email, $source, $stage, $follow_up_date, $notes, $prospect_id, $current_user['team_id']]);
                
                $_SESSION['success_message'] = "Prospect updated successfully!";
            } else {
                $error = "You don't have permission to edit this prospect.";
            }
            header("Location: prospects.php");
            exit();
        }
    } elseif ($_POST['action'] == 'delete_prospect') {
        $prospect_id = $_POST['prospect_id'] ?? 0;
        
        // Check if user has permission to delete this prospect
        if ($current_user['role'] == 'member') {
            $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
            $stmt->execute([$prospect_id, $current_user['team_id'], $current_user['id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ?");
            $stmt->execute([$prospect_id, $current_user['team_id']]);
        }
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM prospects WHERE id = ? AND team_id = ?");
            $stmt->execute([$prospect_id, $current_user['team_id']]);
            
            $_SESSION['success_message'] = "Prospect deleted successfully!";
        } else {
            $error = "You don't have permission to delete this prospect.";
        }
        header("Location: prospects.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get prospects based on user role
if ($current_user['role'] == 'team_leader') {
    // Leaders see all team prospects
    $stmt = $pdo->prepare("SELECT p.*, u.name as owner_name FROM prospects p LEFT JOIN users u ON p.user_id = u.id WHERE p.team_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$current_user['team_id']]);
} else {
    // Members see only their own prospects
    $stmt = $pdo->prepare("SELECT * FROM prospects WHERE team_id = ? AND user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user['team_id'], $current_user['id']]);
}
$all_prospects = $stmt->fetchAll();

// Group prospects by stage
$stages = [
    'new' => ['label' => '🆕 New', 'color' => '#3b82f6', 'icon' => '🆕', 'count' => 0],
    'contacted' => ['label' => '📞 Contacted', 'color' => '#8b5cf6', 'icon' => '📞', 'count' => 0],
    'invited' => ['label' => '✉️ Invited', 'color' => '#ec4899', 'icon' => '✉️', 'count' => 0],
    'presentation' => ['label' => '📊 Presentation', 'color' => '#f59e0b', 'icon' => '📊', 'count' => 0],
    'follow_up' => ['label' => '🔄 Follow Up', 'color' => '#10b981', 'icon' => '🔄', 'count' => 0],
    'joined' => ['label' => '✅ Joined', 'color' => '#059669', 'icon' => '✅', 'count' => 0],
    'not_interested' => ['label' => '❌ Lost', 'color' => '#6b7280', 'icon' => '❌', 'count' => 0]
];

$grouped_prospects = [];
foreach ($stages as $stage_key => $stage_info) {
    $grouped_prospects[$stage_key] = array_filter($all_prospects, function($p) use ($stage_key) {
        return $p['stage'] == $stage_key;
    });
    $stages[$stage_key]['count'] = count($grouped_prospects[$stage_key]);
}

// Get statistics
$total_prospects = count($all_prospects);
$new_prospects = $stages['new']['count'];
$follow_up_today = count(array_filter($all_prospects, function($p) { 
    return $p['follow_up_date'] == date('Y-m-d') && $p['stage'] != 'joined' && $p['stage'] != 'not_interested';
}));
$conversion_rate = $total_prospects > 0 ? round(($stages['joined']['count'] / $total_prospects) * 100) : 0;

// Get trainings for sharing
$stmt = $pdo->prepare("SELECT id, title FROM trainings WHERE team_id = ? ORDER BY created_at DESC");
$stmt->execute([$current_user['team_id']]);
$trainings = $stmt->fetchAll();

// Get current stage from URL or default to 'new'
$current_stage = isset($_GET['stage']) && array_key_exists($_GET['stage'], $stages) ? $_GET['stage'] : 'new';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospects - LeaderDesk</title>
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

        /* Stage Tabs */
        .stage-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
        }

        .stage-tab {
            padding: 12px 20px;
            border-radius: 100px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            text-decoration: none;
            color: #666;
        }

        .stage-tab:hover {
            background: #f5f5f5;
            border-color: #1a1a1a;
        }

        .stage-tab.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .stage-tab .count {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 12px;
            color: #666;
        }

        .stage-tab.active .count {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 20px;
            margin-bottom: 24px;
        }

        .search-box {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
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

        .search-results {
            margin-top: 12px;
            font-size: 13px;
            color: #666;
        }

        /* Prospects Table */
        .prospects-table-container {
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stage-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .prospects-table {
            width: 100%;
            border-collapse: collapse;
        }

        .prospects-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f5f5f5;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            border-bottom: 1px solid #eaeaea;
        }

        .prospects-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eaeaea;
            font-size: 13px;
            vertical-align: middle;
        }

        .prospects-table tr:hover {
            background: #fafafa;
        }

        .prospect-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .prospect-contact {
            font-size: 12px;
            color: #666;
        }

        .follow-up-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .follow-up-today {
            background: #fef3c7;
            color: #92400e;
        }

        .follow-up-overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        .follow-up-future {
            background: #e0f2fe;
            color: #0369a1;
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
            font-size: 12px;
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
            border-color: #3b82f6;
            color: #0369a1;
        }

        .btn-icon.edit:hover {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }

        .btn-icon.share:hover {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }

        .btn-icon.convert:hover {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }

        .btn-icon.delete:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .owner-badge {
            font-size: 11px;
            color: #666;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 100px;
            display: inline-block;
        }

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

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1a1a1a;
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

        .modal-content.large {
            max-width: 600px;
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
            min-height: 100px;
            resize: vertical;
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

        .progress-bar {
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 16px 0;
        }

        .progress-fill {
            height: 100%;
            background: #10b981;
            border-radius: 3px;
            transition: width 0.3s ease;
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

            .search-input {
                width: 100%;
            }

            .prospects-table {
                min-width: 800px;
            }

            .prospects-table-container {
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
                <li class="nav-item">
                    <a href="team.php">
                        <span class="nav-icon">👥</span>
                        Team
                    </a>
                </li>
                <li class="nav-item active">
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
                    <h1>Prospects</h1>
                    <p><?php echo $current_user['role'] == 'team_leader' ? 'Manage team prospects' : 'Manage your prospects'; ?></p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('addProspectModal')">
                        <span>+</span> Add Prospect
                    </button>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Prospects</div>
                    <div class="stat-value"><?php echo $total_prospects; ?></div>
                    <div class="stat-sub">In your pipeline</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">New</div>
                    <div class="stat-value"><?php echo $new_prospects; ?></div>
                    <div class="stat-sub">Ready to contact</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Follow Up Today</div>
                    <div class="stat-value"><?php echo $follow_up_today; ?></div>
                    <div class="stat-sub">Need attention</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Conversion Rate</div>
                    <div class="stat-value"><?php echo $conversion_rate; ?>%</div>
                    <div class="stat-sub">To joined members</div>
                </div>
            </div>

            <!-- Stage Tabs -->
            <div class="stage-tabs">
                <?php foreach ($stages as $stage_key => $stage): ?>
                    <a href="?stage=<?php echo $stage_key; ?>" class="stage-tab <?php echo $current_stage == $stage_key ? 'active' : ''; ?>">
                        <span><?php echo $stage['label']; ?></span>
                        <span class="count"><?php echo $stage['count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name, phone, email, or source..." onkeyup="filterProspects()">
                </div>
                <div class="search-results" id="searchResults">
                    Showing <?php echo count($grouped_prospects[$current_stage]); ?> prospects in <?php echo $stages[$current_stage]['label']; ?>
                </div>
            </div>

            <!-- Prospects Table -->
            <?php if (empty($grouped_prospects[$current_stage])): ?>
                <div class="empty-state">
                    <span><?php echo $stages[$current_stage]['icon']; ?></span>
                    <h3>No prospects in <?php echo $stages[$current_stage]['label']; ?></h3>
                    <p>Add a new prospect or move existing ones to this stage</p>
                    <button class="btn btn-primary" onclick="openModal('addProspectModal')" style="margin-top: 16px;">Add Prospect</button>
                </div>
            <?php else: ?>
                <div class="prospects-table-container">
                    <div class="table-header">
                        <h2>
                            <span class="stage-indicator" style="background: <?php echo $stages[$current_stage]['color']; ?>;"></span>
                            <?php echo $stages[$current_stage]['label']; ?> Prospects
                        </h2>
                    </div>
                    
                    <table class="prospects-table" id="prospectsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Source</th>
                                <th>Follow-up</th>
                                <?php if ($current_user['role'] == 'team_leader'): ?>
                                    <th>Owner</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="prospectsTableBody">
                            <?php foreach ($grouped_prospects[$current_stage] as $prospect): 
                                $follow_up_class = '';
                                $follow_up_text = '';
                                if ($prospect['follow_up_date']) {
                                    if ($prospect['follow_up_date'] == date('Y-m-d')) {
                                        $follow_up_class = 'follow-up-today';
                                        $follow_up_text = 'Today';
                                    } elseif (strtotime($prospect['follow_up_date']) < time()) {
                                        $follow_up_class = 'follow-up-overdue';
                                        $follow_up_text = 'Overdue';
                                    } else {
                                        $follow_up_class = 'follow-up-future';
                                        $follow_up_text = date('M d', strtotime($prospect['follow_up_date']));
                                    }
                                }
                            ?>
                                <tr data-search="<?php echo strtolower($prospect['name'] . ' ' . ($prospect['phone'] ?? '') . ' ' . ($prospect['email'] ?? '') . ' ' . ($prospect['source'] ?? '')); ?>">
                                    <td>
                                        <div class="prospect-name"><?php echo htmlspecialchars($prospect['name']); ?></div>
                                        <?php if ($prospect['notes']): ?>
                                            <small style="color: #666;"><?php echo htmlspecialchars(substr($prospect['notes'], 0, 30)) . (strlen($prospect['notes']) > 30 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($prospect['phone']): ?>
                                            <div>📞 <?php echo htmlspecialchars($prospect['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($prospect['email']): ?>
                                            <div class="prospect-contact">✉️ <?php echo htmlspecialchars($prospect['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($prospect['source']): ?>
                                            <span class="owner-badge"><?php echo htmlspecialchars($prospect['source']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($prospect['follow_up_date']): ?>
                                            <span class="follow-up-badge <?php echo $follow_up_class; ?>">
                                                📅 <?php echo $follow_up_text; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($current_user['role'] == 'team_leader' && isset($prospect['owner_name'])): ?>
                                        <td>
                                            <span class="owner-badge"><?php echo htmlspecialchars($prospect['owner_name']); ?></span>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon view" onclick="viewProspect(<?php echo $prospect['id']; ?>)" title="View Details">👁️</button>
                                            <button class="btn-icon edit" onclick="editProspect(<?php echo $prospect['id']; ?>)" title="Edit">✏️</button>
                                            <?php if ($prospect['stage'] != 'joined'): ?>
                                                <button class="btn-icon convert" onclick="convertProspect(<?php echo $prospect['id']; ?>, '<?php echo htmlspecialchars(addslashes($prospect['name'])); ?>')" title="Convert to Member">✨</button>
                                            <?php endif; ?>
                                            <button class="btn-icon share" onclick="shareFile(<?php echo $prospect['id']; ?>)" title="Share File">📤</button>
                                            <button class="btn-icon delete" onclick="deleteProspect(<?php echo $prospect['id']; ?>)" title="Delete">🗑️</button>
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

    <!-- Add Prospect Modal -->
    <div id="addProspectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Prospect</h2>
                <button class="modal-close" onclick="closeModal('addProspectModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_prospect">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="Enter prospect's name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input" placeholder="Phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="Email address">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" class="form-input" placeholder="Facebook, Referral, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stage</label>
                        <select name="stage" class="form-select">
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="invited">Invited</option>
                            <option value="presentation">Presentation</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="joined">Joined</option>
                            <option value="not_interested">Not Interested</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Follow-up Date</label>
                    <input type="date" name="follow_up_date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" placeholder="Add any notes about this prospect..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addProspectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Prospect</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Prospect Modal -->
    <div id="editProspectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Prospect</h2>
                <button class="modal-close" onclick="closeModal('editProspectModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="editProspectForm">
                <input type="hidden" name="action" value="edit_prospect">
                <input type="hidden" name="prospect_id" id="edit_prospect_id">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" id="edit_source" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stage</label>
                        <select name="stage" id="edit_stage" class="form-select">
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="invited">Invited</option>
                            <option value="presentation">Presentation</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="joined">Joined</option>
                            <option value="not_interested">Not Interested</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Follow-up Date</label>
                    <input type="date" name="follow_up_date" id="edit_follow_up" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editProspectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Prospect</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Prospect Modal -->
    <div id="viewProspectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Prospect Details</h2>
                <button class="modal-close" onclick="closeModal('viewProspectModal')">&times;</button>
            </div>
            
            <div id="viewProspectContent" style="margin: 20px 0; min-height: 200px;">
                <div style="text-align: center; color: #666;">Loading...</div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('viewProspectModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Share File Modal -->
    <div id="shareFileModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2>Share File with Prospect</h2>
                <button class="modal-close" onclick="closeModal('shareFileModal')">&times;</button>
            </div>
            
            <form id="shareFileForm" enctype="multipart/form-data">
                <input type="hidden" name="prospect_id" id="share_prospect_id">
                
                <div class="form-group">
                    <label class="form-label">Select File to Share</label>
                    <input type="file" name="share_file" id="share_file" class="form-input" required>
                    <small style="color: #666; margin-top: 4px; display: block;">
                        Allowed: PDF, Word, Excel, PowerPoint, Images, MP4, MP3, ZIP (Max 50MB)
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message (Optional)</label>
                    <textarea name="message" id="share_message" class="form-textarea" rows="3" placeholder="Add a personal message..."></textarea>
                </div>
                
                <div class="progress-bar" id="uploadProgress" style="display: none;">
                    <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('shareFileModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="shareSubmitBtn">Share File</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Convert Confirmation Modal -->
    <div id="convertConfirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Convert to Member</h2>
                <button class="modal-close" onclick="closeModal('convertConfirmModal')">&times;</button>
            </div>
            
            <div id="convertContent">
                <p>Are you sure you want to convert <span id="convert_name" style="font-weight: 600;"></span> to a team member?</p>
                <p style="color: #f59e0b; margin-top: 10px; padding: 10px; background: #fef3c7; border-radius: 8px;">
                    ⚠️ This will create a user account for them with a temporary password.
                </p>
            </div>
            
            <div id="convertResult" style="display: none;">
                <div class="password-display" style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0;">
                    <p style="margin-bottom: 10px;">✅ Member created successfully!</p>
                    <p style="font-size: 24px; font-weight: 700; font-family: monospace; letter-spacing: 2px; color: #92400e;" id="generated_password"></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please share this password with the new member.</p>
                </div>
            </div>
            
            <div class="modal-footer" id="convertModalFooter">
                <button type="button" class="btn btn-outline" onclick="closeModal('convertConfirmModal')">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmConvert()">Convert to Member</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteProspectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Prospect</h2>
                <button class="modal-close" onclick="closeModal('deleteProspectModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this prospect? This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="action" value="delete_prospect">
                <input type="hidden" name="prospect_id" id="delete_prospect_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteProspectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentProspectId = null;

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function viewProspect(id) {
            const content = document.getElementById('viewProspectContent');
            content.innerHTML = '<div style="text-align: center; color: #666;">Loading...</div>';
            openModal('viewProspectModal');
            
            fetch('ajax/get_prospect.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.prospect;
                        content.innerHTML = `
                            <div style="margin-bottom: 20px;">
                                <h3 style="font-size: 18px; margin-bottom: 15px;">${p.name}</h3>
                                <div style="display: grid; gap: 10px;">
                                    <p><strong>Phone:</strong> ${p.phone || 'Not provided'}</p>
                                    <p><strong>Email:</strong> ${p.email || 'Not provided'}</p>
                                    <p><strong>Source:</strong> ${p.source || 'Not specified'}</p>
                                    <p><strong>Stage:</strong> ${p.stage}</p>
                                    <p><strong>Follow-up Date:</strong> ${p.follow_up_date || 'Not set'}</p>
                                    <p><strong>Notes:</strong></p>
                                    <p style="background: #f5f5f5; padding: 12px; border-radius: 8px;">${p.notes || 'No notes'}</p>
                                    <p><small>Created: ${new Date(p.created_at).toLocaleString()}</small></p>
                                </div>
                            </div>
                        `;
                    } else {
                        content.innerHTML = '<div style="text-align: center; color: #dc2626;">Error loading prospect details.</div>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div style="text-align: center; color: #dc2626;">Error loading prospect details.</div>';
                });
        }

        function editProspect(id) {
            fetch('ajax/get_prospect.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.prospect;
                        document.getElementById('edit_prospect_id').value = p.id;
                        document.getElementById('edit_name').value = p.name || '';
                        document.getElementById('edit_phone').value = p.phone || '';
                        document.getElementById('edit_email').value = p.email || '';
                        document.getElementById('edit_source').value = p.source || '';
                        document.getElementById('edit_stage').value = p.stage || 'new';
                        document.getElementById('edit_follow_up').value = p.follow_up_date || '';
                        document.getElementById('edit_notes').value = p.notes || '';
                        
                        openModal('editProspectModal');
                    } else {
                        alert('Could not load prospect for editing.');
                    }
                })
                .catch(error => {
                    alert('Error loading prospect details.');
                });
        }

        function deleteProspect(id) {
            document.getElementById('delete_prospect_id').value = id;
            openModal('deleteProspectModal');
        }

        function shareFile(id) {
            currentProspectId = id;
            document.getElementById('share_prospect_id').value = id;
            openModal('shareFileModal');
        }

        function convertProspect(id, name) {
            currentProspectId = id;
            document.getElementById('convert_name').textContent = name;
            
            // Reset modal state
            document.getElementById('convertContent').style.display = 'block';
            document.getElementById('convertResult').style.display = 'none';
            document.getElementById('convertModalFooter').style.display = 'flex';
            
            openModal('convertConfirmModal');
        }

        function confirmConvert() {
            fetch('ajax/convert_prospect.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'prospect_id=' + currentProspectId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('convertContent').style.display = 'none';
                    document.getElementById('convertResult').style.display = 'block';
                    document.getElementById('generated_password').textContent = data.password;
                    document.getElementById('convertModalFooter').style.display = 'none';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    alert('Error: ' + data.error);
                    closeModal('convertConfirmModal');
                }
            })
            .catch(error => {
                alert('Error converting prospect.');
                closeModal('convertConfirmModal');
            });
        }

        function filterProspects() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#prospectsTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const searchText = row.dataset.search;
                const match = searchInput === '' || searchText.includes(searchInput);
                
                if (match) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('searchResults').textContent = `Showing ${visibleCount} of ${rows.length} prospects in ${document.querySelector('.stage-tab.active span:first-child').textContent}`;
        }

        // File sharing functionality
        document.getElementById('shareFileForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('shareSubmitBtn');
            const progressBar = document.getElementById('uploadProgress');
            const progressFill = document.getElementById('progressFill');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
            progressBar.style.display = 'block';
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressFill.style.width = percent + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    alert('File shared successfully!');
                    closeModal('shareFileModal');
                    document.getElementById('shareFileForm').reset();
                    progressBar.style.display = 'none';
                    progressFill.style.width = '0%';
                } else {
                    alert('Error: ' + response.error);
                }
                
                submitBtn.disabled = false;
                submitBtn.textContent = 'Share File';
            });
            
            xhr.addEventListener('error', function() {
                alert('Upload failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Share File';
                progressBar.style.display = 'none';
            });
            
            xhr.open('POST', 'ajax/upload_share_file.php', true);
            xhr.send(formData);
        });

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
            filterProspects();
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