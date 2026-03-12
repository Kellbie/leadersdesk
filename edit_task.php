<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$task_id) {
    header("Location: tasks.php");
    exit();
}

// Get task details
$stmt = $pdo->prepare("SELECT t.*, u.name as creator_name, u2.name as assigned_to_name 
                       FROM tasks t 
                       LEFT JOIN users u ON t.created_by = u.id 
                       LEFT JOIN users u2 ON t.assigned_to = u2.id 
                       WHERE t.id = ? AND t.team_id = ?");
$stmt->execute([$task_id, $current_user['team_id']]);
$task = $stmt->fetch();

if (!$task) {
    header("Location: tasks.php");
    exit();
}

// Check if user has permission to edit (team leader or creator)
if ($current_user['role'] != 'team_leader' && $task['created_by'] != $current_user['id']) {
    $_SESSION['error_message'] = "You don't have permission to edit this task.";
    header("Location: tasks.php");
    exit();
}

$message = '';
$error = '';

// Handle task update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_task') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $due_time = $_POST['due_time'] ?? '';
    $location = $_POST['location'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? null;
    $points = $_POST['points'] ?? 10;
    
    if (empty($title) || empty($due_date)) {
        $error = 'Title and due date are required';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, due_time = ?, location = ?, assigned_to = ?, points = ? WHERE id = ?");
            $stmt->execute([$title, $description, $due_date, $due_time, $location, $assigned_to, $points, $task_id]);
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'edit_task', ?, 5)");
            $stmt->execute([$current_user['team_id'], $current_user['id'], "Edited task: $title"]);
            
            $_SESSION['success_message'] = "Task updated successfully!";
            header("Location: tasks.php");
            exit();
        } catch (Exception $e) {
            $error = 'Failed to update task';
        }
    }
}

// Get team members for assignment dropdown
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE team_id = ? AND role = 'member' AND status = 'active' ORDER BY name");
$stmt->execute([$current_user['team_id']]);
$members = $stmt->fetchAll();

$page_title = "Edit Task";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - LeaderDesk</title>
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

        .upgrade-btn {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .upgrade-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .upgrade-btn.pending {
            background: #6b7280;
            cursor: not-allowed;
            pointer-events: none;
        }

        .edit-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .edit-card {
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .card-header {
            margin-bottom: 32px;
        }

        .card-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .card-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 24px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
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

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-secondary:hover {
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .edit-card {
                padding: 24px;
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
                    <h1>Edit Task</h1>
                    <p>Update task details</p>
                </div>
                
                <div class="user-menu">
                    <!-- Upgrade Button for Members -->
                    <?php if ($current_user['role'] == 'member'): ?>
                        <?php
                        $stmt = $pdo->prepare("SELECT upgrade_requested FROM users WHERE id = ?");
                        $stmt->execute([$current_user['id']]);
                        $upgrade_requested = $stmt->fetchColumn();
                        ?>
                        <?php if ($upgrade_requested): ?>
                            <span class="upgrade-btn pending">
                                <span>⏳</span>
                                <span>Upgrade Pending</span>
                            </span>
                        <?php else: ?>
                            <a href="upgrade_request.php" class="upgrade-btn">
                                <span>⬆️</span>
                                <span>Upgrade to Leader</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Notifications -->
                    <div class="notifications">
                        <a href="notifications.php" style="text-decoration: none; color: inherit;">
                            <span>🔔</span>
                            <span class="notification-badge" id="headerNotificationCount" style="display: none;">0</span>
                        </a>
                    </div>
                    
                    <!-- Profile -->
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

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <div class="edit-container">
                <div class="edit-card">
                    <div class="card-header">
                        <h2>Edit Task: <?php echo htmlspecialchars($task['title']); ?></h2>
                        <p>Update the task details below</p>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_task">
                        
                        <div class="form-group">
                            <label class="form-label">Task Title *</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($task['description']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" class="form-input" value="<?php echo $task['due_date']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Due Time</label>
                                <input type="time" name="due_time" class="form-input" value="<?php echo $task['due_time']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-input" value="<?php echo htmlspecialchars($task['location']); ?>" placeholder="Meeting link or venue">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Assign To</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" <?php echo $task['assigned_to'] == $member['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Points</label>
                                <input type="number" name="points" class="form-input" value="<?php echo $task['points']; ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
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