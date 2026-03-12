<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Targets";
$message = '';
$error = '';

// Handle target creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_target') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $target_type = $_POST['target_type'] ?? '';
        $target_value = $_POST['target_value'] ?? 0;
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
        
        if (empty($title) || empty($target_type) || empty($target_value) || empty($start_date) || empty($end_date)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO targets (team_id, created_by, assigned_to, title, description, target_type, target_value, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $assigned_to, $title, $description, $target_type, $target_value, $start_date, $end_date]);
                
                // If assigned to someone, send notification
                if ($assigned_to) {
                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $stmt->execute([$assigned_to]);
                    $assigned_user = $stmt->fetch();
                    
                    if ($assigned_user) {
                        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Target Assigned', ?, 'target', NOW())");
                        $stmt->execute([$current_user['team_id'], $assigned_to, "You have been assigned a new target: $title"]);
                    }
                }
                
                $_SESSION['success_message'] = "Target created successfully!";
                header("Location: targets.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to create target';
            }
        }
    } elseif ($_POST['action'] == 'create_multiple_targets') {
        $targets = $_POST['targets'] ?? [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($targets as $target) {
            $title = $target['title'] ?? '';
            $description = $target['description'] ?? '';
            $target_type = $target['target_type'] ?? '';
            $target_value = $target['target_value'] ?? 0;
            $start_date = $target['start_date'] ?? '';
            $end_date = $target['end_date'] ?? '';
            $assigned_to = !empty($target['assigned_to']) ? $target['assigned_to'] : null;
            
            if (empty($title) || empty($target_type) || empty($target_value) || empty($start_date) || empty($end_date)) {
                $error_count++;
                continue;
            }
            
            try {
                $stmt = $pdo->prepare("INSERT INTO targets (team_id, created_by, assigned_to, title, description, target_type, target_value, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $assigned_to, $title, $description, $target_type, $target_value, $start_date, $end_date]);
                $success_count++;
                
                // Send notification if assigned
                if ($assigned_to) {
                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $stmt->execute([$assigned_to]);
                    $assigned_user = $stmt->fetch();
                    
                    if ($assigned_user) {
                        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Target Assigned', ?, 'target', NOW())");
                        $stmt->execute([$current_user['team_id'], $assigned_to, "You have been assigned a new target: $title"]);
                    }
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "$success_count target(s) created successfully!" . ($error_count > 0 ? " $error_count failed." : "");
        } else {
            $error = "Failed to create targets.";
        }
        header("Location: targets.php");
        exit();
        
    } elseif ($_POST['action'] == 'update_progress') {
        $target_id = $_POST['target_id'] ?? 0;
        $achieved = $_POST['achieved_value'] ?? 0;
        
        // First, get the target to check permissions
        $stmt = $pdo->prepare("SELECT * FROM targets WHERE id = ? AND team_id = ?");
        $stmt->execute([$target_id, $current_user['team_id']]);
        $target = $stmt->fetch();
        
        if ($target) {
            // Check if user has permission to update this target
            $can_update = false;
            
            if ($current_user['role'] == 'team_leader') {
                // Leaders can update any target
                $can_update = true;
            } elseif ($current_user['role'] == 'member') {
                // Members can update if they are assigned to it OR if it's unassigned
                if ($target['assigned_to'] == $current_user['id'] || $target['assigned_to'] === null) {
                    $can_update = true;
                }
            }
            
            if ($can_update) {
                $stmt = $pdo->prepare("UPDATE targets SET achieved_value = ?, last_updated_by = ?, last_updated_at = NOW() WHERE id = ?");
                $stmt->execute([$achieved, $current_user['id'], $target_id]);
                
                // Check if target is completed
                $was_completed = false;
                if ($achieved >= $target['target_value']) {
                    $stmt = $pdo->prepare("UPDATE targets SET status = 'completed' WHERE id = ?");
                    $stmt->execute([$target_id]);
                    $was_completed = true;
                    
                    // Award bonus points for completing target
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'complete_target', ?, 50)");
                    $stmt->execute([$current_user['team_id'], $current_user['id'], "Completed target: {$target['title']}"]);
                    
                    // Notify team leader if completed by member
                    if ($current_user['role'] == 'member') {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
                        $stmt->execute([$current_user['team_id']]);
                        $leader = $stmt->fetch();
                        
                        if ($leader) {
                            $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'Target Completed', ?, 'target', NOW())");
                            $stmt->execute([$current_user['team_id'], $leader['id'], "Member {$current_user['name']} completed target: {$target['title']}"]);
                        }
                    }
                }
                
                $_SESSION['success_message'] = $was_completed ? "🎉 Congratulations! Target completed!" : "✅ Progress updated successfully!";
            } else {
                $error = "You don't have permission to update this target.";
            }
        } else {
            $error = "Target not found.";
        }
        header("Location: targets.php");
        exit();
        
    } elseif ($_POST['action'] == 'delete_target') {
        $target_id = $_POST['target_id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND team_id = ?");
        $stmt->execute([$target_id, $current_user['team_id']]);
        
        $_SESSION['success_message'] = "Target deleted successfully!";
        header("Location: targets.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get team members for assignment dropdown
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE team_id = ? AND role = 'member' AND status = 'active' ORDER BY name");
$stmt->execute([$current_user['team_id']]);
$members = $stmt->fetchAll();

// Get all targets
$stmt = $pdo->prepare("SELECT t.*, 
                       u.name as creator_name, 
                       u2.name as assigned_to_name,
                       u3.name as last_updated_by_name
                       FROM targets t 
                       JOIN users u ON t.created_by = u.id 
                       LEFT JOIN users u2 ON t.assigned_to = u2.id 
                       LEFT JOIN users u3 ON t.last_updated_by = u3.id
                       WHERE t.team_id = ? 
                       ORDER BY 
                         CASE t.status 
                           WHEN 'active' THEN 1
                           WHEN 'completed' THEN 2
                           ELSE 3
                         END,
                         t.end_date ASC");
$stmt->execute([$current_user['team_id']]);
$targets = $stmt->fetchAll();

// Separate targets
$active_targets = array_filter($targets, function($t) { return $t['status'] == 'active'; });
$completed_targets = array_filter($targets, function($t) { return $t['status'] == 'completed'; });

// Group active targets by type
$grouped_targets = [
    'daily' => array_filter($active_targets, function($t) { return $t['target_type'] == 'daily'; }),
    'weekly' => array_filter($active_targets, function($t) { return $t['target_type'] == 'weekly'; }),
    'monthly' => array_filter($active_targets, function($t) { return $t['target_type'] == 'monthly'; })
];

// Statistics
$total_targets = count($targets);
$active_count = count($active_targets);
$completed_count = count($completed_targets);
$my_targets = count(array_filter($active_targets, function($t) use ($current_user) {
    return $t['assigned_to'] == $current_user['id'];
}));
$avg_progress = $active_count > 0 ? round(array_sum(array_map(function($t) { 
    return ($t['achieved_value'] / $t['target_value']) * 100; 
}, $active_targets)) / $active_count) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Targets - LeaderDesk</title>
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

        .targets-section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .section-header .badge {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 13px;
        }

        .targets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .target-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            transition: all 0.2s;
        }

        .target-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .target-card.completed {
            background: #fafafa;
            opacity: 0.8;
        }

        .target-card.my-target {
            border-left: 4px solid #f59e0b;
            background: #fffaf0;
        }

        .target-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .target-title {
            font-size: 18px;
            font-weight: 600;
        }

        .target-type {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .type-daily {
            background: #e0f2fe;
            color: #0369a1;
        }

        .type-weekly {
            background: #fef3c7;
            color: #92400e;
        }

        .type-monthly {
            background: #ecfdf5;
            color: #065f46;
        }

        .assigned-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #f0f0f0;
            border-radius: 100px;
            font-size: 10px;
            margin-left: 8px;
        }

        .target-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .progress-container {
            margin-bottom: 16px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .progress-values {
            font-weight: 600;
        }

        .progress-percent {
            color: #1a1a1a;
            font-weight: 700;
        }

        .progress-bar {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .progress-fill {
            height: 100%;
            background: #1a1a1a;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-fill.completed {
            background: #10b981;
        }

        .target-dates {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #888;
            margin-bottom: 12px;
        }

        .last-updated {
            font-size: 11px;
            color: #888;
            margin-bottom: 16px;
            font-style: italic;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 6px;
        }

        .target-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eaeaea;
            flex-wrap: wrap;
        }

        .btn-update {
            flex: 1;
            padding: 10px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            min-width: 120px;
        }

        .btn-update:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .btn-delete {
            padding: 10px 16px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: #991b1b;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-delete:hover {
            background: #fee2e2;
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
            max-width: 700px;
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

        .progress-preview {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .preview-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .target-entry {
            margin-bottom: 30px;
            padding: 20px;
            background: #fafafa;
            border-radius: 12px;
        }

        .target-entry h4 {
            color: #1a1a1a;
        }

        .remove-target {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 14px;
            padding: 4px 8px;
        }

        .remove-target:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
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

            .targets-grid {
                grid-template-columns: 1fr;
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
                <li class="nav-item active">
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
                    <h1>Targets</h1>
                    <p><?php echo $current_user['role'] == 'member' ? 'Track your personal targets' : 'Set and track team goals'; ?></p>
                </div>
                
                <?php if ($current_user['role'] == 'team_leader'): ?>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('createTargetModal')">
                            <span>+</span> Create Targets
                        </button>
                    </div>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Targets</div>
                    <div class="stat-value"><?php echo $total_targets; ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?php echo $active_count; ?></div>
                    <div class="stat-sub">In progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?php echo $completed_count; ?></div>
                    <div class="stat-sub">Achieved</div>
                </div>
                
                <?php if ($current_user['role'] == 'member'): ?>
                <div class="stat-card" style="background: #fffaf0; border-color: #f59e0b;">
                    <div class="stat-label">My Targets</div>
                    <div class="stat-value" style="color: #f59e0b;"><?php echo $my_targets; ?></div>
                    <div class="stat-sub">Assigned to you</div>
                </div>
                <?php else: ?>
                <div class="stat-card">
                    <div class="stat-label">Avg Progress</div>
                    <div class="stat-value"><?php echo $avg_progress; ?>%</div>
                    <div class="stat-sub">Across active targets</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Active Targets -->
            <?php if (empty($active_targets)): ?>
                <div class="empty-state">
                    <span>🎯</span>
                    <h3>No active targets</h3>
                    <?php if ($current_user['role'] == 'member'): ?>
                        <p>You don't have any targets assigned yet.</p>
                    <?php else: ?>
                        <p>Create your first target to start tracking progress</p>
                        <button class="btn btn-primary" onclick="openModal('createTargetModal')">Create Target</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Daily Targets -->
                <?php if (!empty($grouped_targets['daily'])): ?>
                    <div class="targets-section">
                        <div class="section-header">
                            <h2>Daily Targets</h2>
                            <span class="badge"><?php echo count($grouped_targets['daily']); ?> active</span>
                        </div>
                        
                        <div class="targets-grid">
                            <?php foreach ($grouped_targets['daily'] as $target): 
                                $is_my_target = ($target['assigned_to'] == $current_user['id']);
                                $can_update = ($current_user['role'] == 'team_leader' || $is_my_target || $target['assigned_to'] === null);
                            ?>
                                <div class="target-card <?php echo $is_my_target ? 'my-target' : ''; ?>">
                                    <div class="target-header">
                                        <h3 class="target-title">
                                            <?php echo htmlspecialchars($target['title']); ?>
                                            <?php if ($target['assigned_to_name']): ?>
                                                <span class="assigned-badge">for <?php echo htmlspecialchars($target['assigned_to_name']); ?></span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="target-type type-daily">Daily</span>
                                    </div>
                                    
                                    <?php if ($target['description']): ?>
                                        <p class="target-description"><?php echo htmlspecialchars($target['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span class="progress-values"><?php echo $target['achieved_value']; ?> / <?php echo $target['target_value']; ?></span>
                                            <span class="progress-percent"><?php echo round(($target['achieved_value'] / $target['target_value']) * 100); ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $target['status'] == 'completed' ? 'completed' : ''; ?>" style="width: <?php echo ($target['achieved_value'] / $target['target_value']) * 100; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="target-dates">
                                        <span>📅 <?php echo date('M d', strtotime($target['start_date'])); ?></span>
                                        <span>→ <?php echo date('M d', strtotime($target['end_date'])); ?></span>
                                    </div>
                                    
                                    <?php if ($target['last_updated_by_name']): ?>
                                        <div class="last-updated">
                                            Last updated by <?php echo htmlspecialchars($target['last_updated_by_name']); ?> on <?php echo date('M d, g:i A', strtotime($target['last_updated_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="target-actions">
                                        <?php if ($can_update): ?>
                                            <button class="btn-update" onclick="updateProgress(<?php echo $target['id']; ?>, <?php echo $target['target_value']; ?>, <?php echo $target['achieved_value']; ?>)">
                                                📈 Update Progress
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($current_user['role'] == 'team_leader'): ?>
                                            <button class="btn-delete" onclick="deleteTarget(<?php echo $target['id']; ?>)">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Weekly Targets -->
                <?php if (!empty($grouped_targets['weekly'])): ?>
                    <div class="targets-section">
                        <div class="section-header">
                            <h2>Weekly Targets</h2>
                            <span class="badge"><?php echo count($grouped_targets['weekly']); ?> active</span>
                        </div>
                        
                        <div class="targets-grid">
                            <?php foreach ($grouped_targets['weekly'] as $target): 
                                $is_my_target = ($target['assigned_to'] == $current_user['id']);
                                $can_update = ($current_user['role'] == 'team_leader' || $is_my_target || $target['assigned_to'] === null);
                            ?>
                                <div class="target-card <?php echo $is_my_target ? 'my-target' : ''; ?>">
                                    <div class="target-header">
                                        <h3 class="target-title">
                                            <?php echo htmlspecialchars($target['title']); ?>
                                            <?php if ($target['assigned_to_name']): ?>
                                                <span class="assigned-badge">for <?php echo htmlspecialchars($target['assigned_to_name']); ?></span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="target-type type-weekly">Weekly</span>
                                    </div>
                                    
                                    <?php if ($target['description']): ?>
                                        <p class="target-description"><?php echo htmlspecialchars($target['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span class="progress-values"><?php echo $target['achieved_value']; ?> / <?php echo $target['target_value']; ?></span>
                                            <span class="progress-percent"><?php echo round(($target['achieved_value'] / $target['target_value']) * 100); ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $target['status'] == 'completed' ? 'completed' : ''; ?>" style="width: <?php echo ($target['achieved_value'] / $target['target_value']) * 100; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="target-dates">
                                        <span>📅 <?php echo date('M d', strtotime($target['start_date'])); ?></span>
                                        <span>→ <?php echo date('M d', strtotime($target['end_date'])); ?></span>
                                    </div>
                                    
                                    <?php if ($target['last_updated_by_name']): ?>
                                        <div class="last-updated">
                                            Last updated by <?php echo htmlspecialchars($target['last_updated_by_name']); ?> on <?php echo date('M d, g:i A', strtotime($target['last_updated_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="target-actions">
                                        <?php if ($can_update): ?>
                                            <button class="btn-update" onclick="updateProgress(<?php echo $target['id']; ?>, <?php echo $target['target_value']; ?>, <?php echo $target['achieved_value']; ?>)">
                                                📈 Update Progress
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($current_user['role'] == 'team_leader'): ?>
                                            <button class="btn-delete" onclick="deleteTarget(<?php echo $target['id']; ?>)">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Monthly Targets -->
                <?php if (!empty($grouped_targets['monthly'])): ?>
                    <div class="targets-section">
                        <div class="section-header">
                            <h2>Monthly Targets</h2>
                            <span class="badge"><?php echo count($grouped_targets['monthly']); ?> active</span>
                        </div>
                        
                        <div class="targets-grid">
                            <?php foreach ($grouped_targets['monthly'] as $target): 
                                $is_my_target = ($target['assigned_to'] == $current_user['id']);
                                $can_update = ($current_user['role'] == 'team_leader' || $is_my_target || $target['assigned_to'] === null);
                            ?>
                                <div class="target-card <?php echo $is_my_target ? 'my-target' : ''; ?>">
                                    <div class="target-header">
                                        <h3 class="target-title">
                                            <?php echo htmlspecialchars($target['title']); ?>
                                            <?php if ($target['assigned_to_name']): ?>
                                                <span class="assigned-badge">for <?php echo htmlspecialchars($target['assigned_to_name']); ?></span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="target-type type-monthly">Monthly</span>
                                    </div>
                                    
                                    <?php if ($target['description']): ?>
                                        <p class="target-description"><?php echo htmlspecialchars($target['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span class="progress-values"><?php echo $target['achieved_value']; ?> / <?php echo $target['target_value']; ?></span>
                                            <span class="progress-percent"><?php echo round(($target['achieved_value'] / $target['target_value']) * 100); ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $target['status'] == 'completed' ? 'completed' : ''; ?>" style="width: <?php echo ($target['achieved_value'] / $target['target_value']) * 100; ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="target-dates">
                                        <span>📅 <?php echo date('M d', strtotime($target['start_date'])); ?></span>
                                        <span>→ <?php echo date('M d', strtotime($target['end_date'])); ?></span>
                                    </div>
                                    
                                    <?php if ($target['last_updated_by_name']): ?>
                                        <div class="last-updated">
                                            Last updated by <?php echo htmlspecialchars($target['last_updated_by_name']); ?> on <?php echo date('M d, g:i A', strtotime($target['last_updated_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="target-actions">
                                        <?php if ($can_update): ?>
                                            <button class="btn-update" onclick="updateProgress(<?php echo $target['id']; ?>, <?php echo $target['target_value']; ?>, <?php echo $target['achieved_value']; ?>)">
                                                📈 Update Progress
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($current_user['role'] == 'team_leader'): ?>
                                            <button class="btn-delete" onclick="deleteTarget(<?php echo $target['id']; ?>)">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Completed Targets -->
                <?php if (!empty($completed_targets)): ?>
                    <div class="targets-section">
                        <div class="section-header">
                            <h2>Completed Targets</h2>
                            <span class="badge"><?php echo count($completed_targets); ?> completed</span>
                        </div>
                        
                        <div class="targets-grid">
                            <?php foreach ($completed_targets as $target): ?>
                                <div class="target-card completed">
                                    <div class="target-header">
                                        <h3 class="target-title">
                                            <?php echo htmlspecialchars($target['title']); ?>
                                            <?php if ($target['assigned_to_name']): ?>
                                                <span class="assigned-badge">for <?php echo htmlspecialchars($target['assigned_to_name']); ?></span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="target-type type-<?php echo $target['target_type']; ?>">
                                            <?php echo ucfirst($target['target_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="progress-container">
                                        <div class="progress-header">
                                            <span class="progress-values"><?php echo $target['achieved_value']; ?> / <?php echo $target['target_value']; ?></span>
                                            <span class="progress-percent">100%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill completed" style="width: 100%;"></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($target['last_updated_by_name']): ?>
                                        <div class="last-updated">
                                            Completed by: <?php echo htmlspecialchars($target['last_updated_by_name']); ?>
                                            on <?php echo date('M d, Y', strtotime($target['last_updated_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create Target Modal (Multiple Entries) -->
    <div id="createTargetModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Create New Targets</h2>
                <button class="modal-close" onclick="closeModal('createTargetModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="targetForm">
                <input type="hidden" name="action" value="create_multiple_targets">
                
                <div id="targets-container">
                    <!-- Target Entry 1 (default) -->
                    <div class="target-entry" id="target-entry-1">
                        <h4 style="margin-bottom: 15px; display: flex; justify-content: space-between;">
                            Target #1
                            <button type="button" class="remove-target" style="display: none;" onclick="removeTarget(1)">Remove</button>
                        </h4>
                        
                        <div class="form-group">
                            <label class="form-label">Target Title *</label>
                            <input type="text" name="targets[0][title]" class="form-input" required placeholder="e.g., Recruit 5 new members">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="targets[0][description]" class="form-textarea" placeholder="Describe what this target entails..."></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Assign To</label>
                                <select name="targets[0][assigned_to]" class="form-select">
                                    <option value="">Everyone (Team Target)</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Target Type *</label>
                                <select name="targets[0][target_type]" class="form-select" required>
                                    <option value="">Select type</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Target Value *</label>
                                <input type="number" name="targets[0][target_value]" class="form-input" required min="1" placeholder="e.g., 5">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="targets[0][start_date]" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">End Date *</label>
                                <input type="date" name="targets[0][end_date]" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <hr style="margin: 20px 0; border: 1px dashed #eaeaea;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <button type="button" id="add-target-btn" class="btn btn-outline">+ Add Another Target</button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createTargetModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save All Targets</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Progress Modal -->
    <div id="updateProgressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Progress</h2>
                <button class="modal-close" onclick="closeModal('updateProgressModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="target_id" id="update_target_id">
                
                <div class="form-group">
                    <label class="form-label">Current Achievement</label>
                    <input type="number" name="achieved_value" id="achieved_value" class="form-input" required min="0" step="0.01" value="0">
                </div>
                
                <div class="progress-preview">
                    <div class="preview-label">
                        <span>Progress will be:</span>
                        <span id="progress_percent">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress_preview" style="width: 0%"></div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('updateProgressModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Progress</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTargetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Target</h2>
                <button class="modal-close" onclick="closeModal('deleteTargetModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this target? This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteTargetForm">
                <input type="hidden" name="action" value="delete_target">
                <input type="hidden" name="target_id" id="delete_target_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteTargetModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete Target</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let targetCount = 1;
        let currentMaxValue = 0;
        let currentAchieved = 0;

        document.getElementById('add-target-btn')?.addEventListener('click', function() {
            targetCount++;
            const container = document.getElementById('targets-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'target-entry';
            newEntry.id = `target-entry-${targetCount}`;
            
            // Get member options for the select
            const memberOptions = <?php echo json_encode(array_map(function($m) {
                return ['id' => $m['id'], 'name' => $m['name']];
            }, $members)); ?>;
            
            let memberSelectHtml = '<option value="">Everyone (Team Target)</option>';
            memberOptions.forEach(m => {
                memberSelectHtml += `<option value="${m.id}">${m.name}</option>`;
            });
            
            newEntry.innerHTML = `
                <h4 style="margin-bottom: 15px; display: flex; justify-content: space-between;">
                    Target #${targetCount}
                    <button type="button" class="remove-target" style="background: none; border: none; color: #dc2626; cursor: pointer;" onclick="removeTarget(${targetCount})">Remove</button>
                </h4>
                
                <div class="form-group">
                    <label class="form-label">Target Title *</label>
                    <input type="text" name="targets[${targetCount-1}][title]" class="form-input" required placeholder="e.g., Recruit 5 new members">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="targets[${targetCount-1}][description]" class="form-textarea" placeholder="Describe what this target entails..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign To</label>
                        <select name="targets[${targetCount-1}][assigned_to]" class="form-select">
                            ${memberSelectHtml}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Target Type *</label>
                        <select name="targets[${targetCount-1}][target_type]" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Target Value *</label>
                        <input type="number" name="targets[${targetCount-1}][target_value]" class="form-input" required min="1" placeholder="e.g., 5">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="targets[${targetCount-1}][start_date]" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="targets[${targetCount-1}][end_date]" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <hr style="margin: 20px 0; border: 1px dashed #eaeaea;">
            `;
            container.appendChild(newEntry);
        });

        function removeTarget(id) {
            const element = document.getElementById(`target-entry-${id}`);
            if (element) {
                element.remove();
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function updateProgress(targetId, maxValue, achievedValue) {
            currentMaxValue = maxValue;
            currentAchieved = achievedValue;
            document.getElementById('update_target_id').value = targetId;
            document.getElementById('achieved_value').max = maxValue;
            document.getElementById('achieved_value').value = achievedValue;
            updateProgressPreview();
            openModal('updateProgressModal');
        }

        function updateProgressPreview() {
            const achieved = document.getElementById('achieved_value').value || 0;
            const percent = (achieved / currentMaxValue) * 100;
            
            document.getElementById('progress_percent').textContent = Math.round(percent) + '%';
            document.getElementById('progress_preview').style.width = percent + '%';
        }

        document.getElementById('achieved_value')?.addEventListener('input', updateProgressPreview);

        function deleteTarget(targetId) {
            document.getElementById('delete_target_id').value = targetId;
            openModal('deleteTargetModal');
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