<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Tasks";
$message = '';
$error = '';

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_task') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $due_time = $_POST['due_time'] ?? '';
        $location = $_POST['location'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?? null;
        $points = $_POST['points'] ?? 10;
        
        // Handle file upload
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = 'uploads/tasks/';
            // Create the directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = time() . '_' . basename($_FILES['attachment']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = $target_file;
            } else {
                $error = 'Failed to upload file.';
            }
        }
        
        if (empty($title) || empty($due_date)) {
            $error = 'Title and due date are required';
        } else if (empty($error)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO tasks (team_id, created_by, assigned_to, title, description, due_date, due_time, location, attachment, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $assigned_to, $title, $description, $due_date, $due_time, $location, $attachment_path, $points]);
                $task_id = $pdo->lastInsertId();
                
                // Create notification for assigned user
                if ($assigned_to) {
                    $task_link = 'tasks.php?view_task=' . $task_id;
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, link, created_at) VALUES (?, ?, 'New Task Assigned', ?, 'task', ?, NOW())");
                    $stmt->execute([$current_user['team_id'], $assigned_to, "You have been assigned a new task: $title", $task_link]);
                }
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'create_task', ?, 5)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], "Created task: $title"]);
                
                $_SESSION['success_message'] = "Task created successfully!";
                header("Location: tasks.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to create task';
            }
        }
    } elseif ($_POST['action'] == 'toggle_task') {
        $task_id = $_POST['task_id'] ?? 0;
        $new_status = $_POST['status'] ?? 'pending';
        
        $stmt = $pdo->prepare("SELECT points, title, assigned_to FROM tasks WHERE id = ? AND team_id = ?");
        $stmt->execute([$task_id, $current_user['team_id']]);
        $task = $stmt->fetch();
        
        if ($task) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);
            
            if ($new_status == 'completed') {
                // Award points to the person who completed it
                $points_user = $task['assigned_to'] ?: $current_user['id'];
                
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'complete_task', ?, ?)");
                $stmt->execute([$current_user['team_id'], $points_user, "Completed task: {$task['title']}", $task['points']]);
                
                // Update member activity score
                $stmt = $pdo->prepare("UPDATE member_profiles SET activity_score = activity_score + ? WHERE user_id = ?");
                $stmt->execute([$task['points'], $points_user]);
                
                $_SESSION['success_message'] = "Task completed! You earned {$task['points']} points!";
            } else {
                // Remove points when uncompleting
                $points_user = $task['assigned_to'] ?: $current_user['id'];
                
                $stmt = $pdo->prepare("UPDATE member_profiles SET activity_score = activity_score - ? WHERE user_id = ?");
                $stmt->execute([$task['points'], $points_user]);
                
                $_SESSION['success_message'] = "Task marked as pending.";
            }
            
            header("Location: tasks.php");
            exit();
        }
    } elseif ($_POST['action'] == 'delete_task') {
        $task_id = $_POST['task_id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND team_id = ?");
        $stmt->execute([$task_id, $current_user['team_id']]);
        
        $_SESSION['success_message'] = "Task deleted successfully!";
        header("Location: tasks.php");
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

// Get tasks based on role
if ($current_user['role'] == 'member') {
    $stmt = $pdo->prepare("SELECT t.*, u.name as creator_name, u2.name as assigned_to_name 
                          FROM tasks t 
                          JOIN users u ON t.created_by = u.id 
                          LEFT JOIN users u2 ON t.assigned_to = u2.id 
                          WHERE t.team_id = ? AND (t.assigned_to = ? OR t.assigned_to IS NULL) 
                          ORDER BY t.status, t.due_date ASC");
    $stmt->execute([$current_user['team_id'], $current_user['id']]);
} else {
    $stmt = $pdo->prepare("SELECT t.*, u.name as creator_name, u2.name as assigned_to_name 
                          FROM tasks t 
                          JOIN users u ON t.created_by = u.id 
                          LEFT JOIN users u2 ON t.assigned_to = u2.id 
                          WHERE t.team_id = ? 
                          ORDER BY t.status, t.due_date ASC");
    $stmt->execute([$current_user['team_id']]);
}
$tasks = $stmt->fetchAll();

// Check if viewing a specific task
$view_task_id = isset($_GET['view_task']) ? (int)$_GET['view_task'] : 0;
$view_task = null;
if ($view_task_id) {
    foreach ($tasks as $task) {
        if ($task['id'] == $view_task_id) {
            $view_task = $task;
            break;
        }
    }
}

$pending_tasks = array_filter($tasks, function($t) { return $t['status'] == 'pending'; });
$completed_tasks = array_filter($tasks, function($t) { return $t['status'] == 'completed'; });

$total_tasks = count($tasks);
$pending_count = count($pending_tasks);
$completed_count = count($completed_tasks);
$overdue_count = count(array_filter($pending_tasks, function($t) { 
    return strtotime($t['due_date']) < time(); 
}));
$my_tasks = count(array_filter($pending_tasks, function($t) use ($current_user) { 
    return $t['assigned_to'] == $current_user['id']; 
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - LeaderDesk</title>
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

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 100px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #f5f5f5;
        }

        .filter-btn.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .tasks-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .task-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.2s;
            animation: fadeIn 0.3s ease-out;
            cursor: pointer;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .task-card.completed {
            opacity: 0.7;
            background: #fafafa;
        }

        .task-check {
            width: 30px;
            height: 30px;
            border: 2px solid #eaeaea;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 16px;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }

        .task-check:hover {
            border-color: #10b981;
            background: #f0f0f0;
        }

        .task-check.completed {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }

        .task-check.completed:hover {
            background: #dc2626;
            border-color: #dc2626;
        }

        .task-content {
            flex: 1;
        }

        .task-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .task-title {
            font-size: 18px;
            font-weight: 600;
        }

        .task-title.completed {
            text-decoration: line-through;
            color: #888;
        }

        .task-priority {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-low {
            background: #e0f2fe;
            color: #0369a1;
        }

        .task-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .task-meta {
            display: flex;
            gap: 24px;
            font-size: 13px;
            color: #888;
            flex-wrap: wrap;
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .task-meta-item.overdue {
            color: #dc2626;
        }

        .task-attachment {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #eaeaea;
        }

        .attachment-link {
            color: #1a1a1a;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        .task-points {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 100px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .task-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-icon {
            background: white;
            border: 1px solid #eaeaea;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: #f5f5f5;
        }

        /* Task Detail Modal */
        .task-detail-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .task-detail-modal.show {
            display: flex;
        }

        .task-detail-content {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        .task-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .task-detail-header h2 {
            font-size: 24px;
            font-weight: 700;
        }

        .task-detail-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .task-detail-close:hover {
            color: #1a1a1a;
        }

        .task-detail-section {
            margin-bottom: 24px;
        }

        .task-detail-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #666;
        }

        .task-detail-info {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 20px;
        }

        .task-detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .task-detail-label {
            width: 100px;
            font-weight: 600;
            color: #666;
        }

        .task-detail-value {
            flex: 1;
            color: #1a1a1a;
        }

        .task-detail-description {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 12px;
            line-height: 1.6;
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
            max-width: 600px;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .task-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .task-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .task-detail-row {
                flex-direction: column;
            }

            .task-detail-label {
                width: auto;
                margin-bottom: 4px;
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
                <li class="nav-item active">
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
                    <h1>Tasks</h1>
                    <p>Manage and track your tasks</p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('createTaskModal')">
                        <span>+</span> Create Task
                    </button>
                    <?php if ($current_user['role'] == 'member'): ?>
                        <button class="btn btn-outline" onclick="filterTasks('assigned')">
                            <span>👤</span> My Tasks (<?php echo $my_tasks; ?>)
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Tasks</div>
                    <div class="stat-value"><?php echo $total_tasks; ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                    <div class="stat-sub">Need attention</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?php echo $completed_count; ?></div>
                    <div class="stat-sub">This month</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value" style="color: #dc2626;"><?php echo $overdue_count; ?></div>
                    <div class="stat-sub">Past due date</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <button class="filter-btn active" onclick="filterTasks('all')">All Tasks</button>
                <button class="filter-btn" onclick="filterTasks('pending')">Pending</button>
                <button class="filter-btn" onclick="filterTasks('completed')">Completed</button>
                <button class="filter-btn" onclick="filterTasks('overdue')">Overdue</button>
                <button class="filter-btn" onclick="filterTasks('assigned')">Assigned to me</button>
            </div>

            <!-- Tasks List -->
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <span>✅</span>
                    <h3>No tasks yet</h3>
                    <p>Create your first task to get started</p>
                    <button class="btn btn-primary" onclick="openModal('createTaskModal')">Create Task</button>
                </div>
            <?php else: ?>
                <div class="tasks-container" id="tasksContainer">
                    <?php foreach ($tasks as $task): 
                        $is_pending = $task['status'] == 'pending';
                    ?>
                        <div class="task-card <?php echo !$is_pending ? 'completed' : ''; ?>" data-status="<?php echo $task['status']; ?>" data-assigned="<?php echo $task['assigned_to']; ?>" onclick="viewTaskDetail(<?php echo $task['id']; ?>)">
                            <div class="task-check <?php echo !$is_pending ? 'completed' : ''; ?>" 
                                 onclick="event.stopPropagation(); toggleTask(<?php echo $task['id']; ?>, '<?php echo $task['status']; ?>')">
                                <?php if (!$is_pending): ?>✓<?php endif; ?>
                            </div>
                            
                            <div class="task-content">
                                <div class="task-header">
                                    <h3 class="task-title <?php echo !$is_pending ? 'completed' : ''; ?>">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </h3>
                                    <?php if ($is_pending): ?>
                                    <span class="task-priority priority-<?php 
                                        echo strtotime($task['due_date']) < time() ? 'high' : 
                                            (strtotime($task['due_date']) < strtotime('+3 days') ? 'medium' : 'low'); 
                                    ?>">
                                        <?php 
                                        if (strtotime($task['due_date']) < time()) echo 'Overdue';
                                        elseif (strtotime($task['due_date']) < strtotime('+3 days')) echo 'Urgent';
                                        else echo 'Upcoming';
                                        ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($task['description']): ?>
                                    <p class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <div class="task-meta">
                                    <?php if ($is_pending): ?>
                                    <div class="task-meta-item <?php echo strtotime($task['due_date']) < time() ? 'overdue' : ''; ?>">
                                        <span>📅</span>
                                        <span>Due <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                        <?php if ($task['due_time']): ?>
                                            <span>at <?php echo date('g:i A', strtotime($task['due_time'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="task-meta-item">
                                        <span>✅</span>
                                        <span>Completed</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['assigned_to_name']): ?>
                                        <div class="task-meta-item">
                                            <span>👤</span>
                                            <span><?php echo htmlspecialchars($task['assigned_to_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="task-points">
                                        🏆 <?php echo $task['points']; ?> pts
                                    </div>
                                </div>
                                
                                <?php if ($task['attachment']): ?>
                                    <div class="task-attachment" onclick="event.stopPropagation();">
                                        <a href="<?php echo htmlspecialchars($task['attachment']); ?>" target="_blank" class="attachment-link">
                                            📎 View Attachment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="task-actions" onclick="event.stopPropagation();">
                                <button class="btn-icon" onclick="editTask(<?php echo $task['id']; ?>)">✏️</button>
                                <?php if ($current_user['role'] == 'team_leader'): ?>
                                    <button class="btn-icon" onclick="deleteTask(<?php echo $task['id']; ?>)">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create Task Modal -->
    <div id="createTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <button class="modal-close" onclick="closeModal('createTaskModal')">&times;</button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_task">
                
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" name="title" class="form-input" required placeholder="e.g., Follow up with prospect">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Add details about this task..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Due Time</label>
                        <input type="time" name="due_time" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-input" placeholder="Meeting link or venue">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Attachment (Optional)</label>
                    <input type="file" name="attachment" class="form-input">
                    <small style="color: #666;">You can upload images, PDFs, or documents.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Points</label>
                        <input type="number" name="points" class="form-input" value="10" min="1" max="100">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Task</h2>
                <button class="modal-close" onclick="closeModal('deleteTaskModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this task? This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteTaskForm">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" id="delete_task_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteTaskModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div id="taskDetailModal" class="task-detail-modal">
        <div class="task-detail-content">
            <div class="task-detail-header">
                <h2 id="detailTaskTitle">Task Details</h2>
                <button class="task-detail-close" onclick="closeTaskDetail()">&times;</button>
            </div>
            
            <div id="taskDetailContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function toggleTask(taskId, currentStatus) {
            const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_task">
                <input type="hidden" name="task_id" value="${taskId}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function editTask(taskId) {
            window.location.href = 'edit_task.php?id=' + taskId;
        }

        function deleteTask(taskId) {
            document.getElementById('delete_task_id').value = taskId;
            openModal('deleteTaskModal');
        }

        function viewTaskDetail(taskId) {
            // Find the task from our data
            const tasks = <?php echo json_encode($tasks); ?>;
            const task = tasks.find(t => t.id == taskId);
            
            if (!task) return;
            
            const isPending = task.status === 'pending';
            
            let priorityClass = '';
            let priorityText = '';
            if (isPending) {
                const dueDate = new Date(task.due_date);
                const today = new Date();
                const threeDaysFromNow = new Date();
                threeDaysFromNow.setDate(today.getDate() + 3);
                
                if (dueDate < today) {
                    priorityClass = 'priority-high';
                    priorityText = 'Overdue';
                } else if (dueDate < threeDaysFromNow) {
                    priorityClass = 'priority-medium';
                    priorityText = 'Urgent';
                } else {
                    priorityClass = 'priority-low';
                    priorityText = 'Upcoming';
                }
            }
            
            const content = document.getElementById('taskDetailContent');
            content.innerHTML = `
                <div class="task-detail-section">
                    <h3>Description</h3>
                    <div class="task-detail-description">
                        ${task.description ? task.description : 'No description provided.'}
                    </div>
                </div>
                
                <div class="task-detail-section">
                    <h3>Details</h3>
                    <div class="task-detail-info">
                        <div class="task-detail-row">
                            <span class="task-detail-label">Status:</span>
                            <span class="task-detail-value">
                                <span class="task-priority ${priorityClass}" style="display: inline-block; margin-left: 0;">
                                    ${isPending ? priorityText : 'Completed'}
                                </span>
                            </span>
                        </div>
                        
                        <div class="task-detail-row">
                            <span class="task-detail-label">Due Date:</span>
                            <span class="task-detail-value">
                                ${new Date(task.due_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                                ${task.due_time ? ' at ' + new Date('2000-01-01T' + task.due_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                            </span>
                        </div>
                        
                        ${task.assigned_to_name ? `
                        <div class="task-detail-row">
                            <span class="task-detail-label">Assigned To:</span>
                            <span class="task-detail-value">${task.assigned_to_name}</span>
                        </div>
                        ` : ''}
                        
                        <div class="task-detail-row">
                            <span class="task-detail-label">Created By:</span>
                            <span class="task-detail-value">${task.creator_name}</span>
                        </div>
                        
                        <div class="task-detail-row">
                            <span class="task-detail-label">Points:</span>
                            <span class="task-detail-value">${task.points} pts</span>
                        </div>
                        
                        ${task.location ? `
                        <div class="task-detail-row">
                            <span class="task-detail-label">Location:</span>
                            <span class="task-detail-value">${task.location}</span>
                        </div>
                        ` : ''}
                        
                        ${task.attachment ? `
                        <div class="task-detail-row">
                            <span class="task-detail-label">Attachment:</span>
                            <span class="task-detail-value">
                                <a href="${task.attachment}" target="_blank" style="color: #1a1a1a;">View Attachment</a>
                            </span>
                        </div>
                        ` : ''}
                        
                        <div class="task-detail-row">
                            <span class="task-detail-label">Created:</span>
                            <span class="task-detail-value">${new Date(task.created_at).toLocaleString()}</span>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detailTaskTitle').textContent = task.title;
            document.getElementById('taskDetailModal').classList.add('show');
        }

        function closeTaskDetail() {
            document.getElementById('taskDetailModal').classList.remove('show');
        }

        function filterTasks(filter) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const tasks = document.querySelectorAll('.task-card');
            tasks.forEach(task => {
                const status = task.dataset.status;
                const assigned = task.dataset.assigned;
                
                switch(filter) {
                    case 'all':
                        task.style.display = 'flex';
                        break;
                    case 'pending':
                        task.style.display = status === 'pending' ? 'flex' : 'none';
                        break;
                    case 'completed':
                        task.style.display = status === 'completed' ? 'flex' : 'none';
                        break;
                    case 'overdue':
                        const isOverdue = task.querySelector('.task-meta-item.overdue');
                        task.style.display = isOverdue ? 'flex' : 'none';
                        break;
                    case 'assigned':
                        task.style.display = assigned === '<?php echo $current_user['id']; ?>' ? 'flex' : 'none';
                        break;
                }
            });
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
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
            
            // Check if we need to show a task from notification
            <?php if ($view_task_id && $view_task): ?>
            viewTaskDetail(<?php echo $view_task_id; ?>);
            <?php endif; ?>
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
            if (event.target.classList.contains('task-detail-modal')) {
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