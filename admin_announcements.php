<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Announcements";
$message = '';
$error = '';

// Create announcements table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target VARCHAR(100) NOT NULL,
        priority VARCHAR(50) DEFAULT 'normal',
        sent_to_count INT DEFAULT 0,
        sent_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sent_by),
        INDEX (created_at)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Handle announcement sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'send_announcement') {
        $title = $_POST['title'] ?? '';
        $message_content = $_POST['message'] ?? '';
        $target = $_POST['target'] ?? 'all';
        $priority = $_POST['priority'] ?? 'normal';
        
        if (empty($title) || empty($message_content)) {
            $error = 'Title and message are required';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get target users based on selection
                if ($target == 'all') {
                    $stmt = $pdo->query("SELECT id, team_id FROM users WHERE status = 'active' AND team_id IS NOT NULL");
                } elseif ($target == 'leaders') {
                    $stmt = $pdo->query("SELECT id, team_id FROM users WHERE role = 'team_leader' AND status = 'active' AND team_id IS NOT NULL");
                } elseif ($target == 'members') {
                    $stmt = $pdo->query("SELECT id, team_id FROM users WHERE role = 'member' AND status = 'active' AND team_id IS NOT NULL");
                } elseif (strpos($target, 'team_') === 0) {
                    $team_id = str_replace('team_', '', $target);
                    $stmt = $pdo->prepare("SELECT id, team_id FROM users WHERE team_id = ? AND status = 'active' AND team_id IS NOT NULL");
                    $stmt->execute([$team_id]);
                } else {
                    $stmt = $pdo->query("SELECT id, team_id FROM users WHERE status = 'active' AND team_id IS NOT NULL");
                }
                
                $users = $stmt->fetchAll();
                $sent_count = 0;
                
                // Send notification to each user
                foreach ($users as $user) {
                    $team_id = $user['team_id'];
                    
                    $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, 'announcement', NOW())");
                    $stmt->execute([$team_id, $user['id'], $title, $message_content]);
                    $sent_count++;
                }
                
                // Save announcement to history
                $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target, priority, sent_to_count, sent_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$title, $message_content, $target, $priority, $sent_count, $current_user['id']]);
                
                // Log the announcement - use a default team_id (1) for super admin since they have no team
                // First, check if team 1 exists
                $stmt = $pdo->query("SELECT id FROM teams LIMIT 1");
                $first_team = $stmt->fetch();
                $log_team_id = $first_team ? $first_team['id'] : 1;
                
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'announcement', ?, 0)");
                $stmt->execute([$log_team_id, $current_user['id'], "Sent announcement: $title to $sent_count users"]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "Announcement sent to $sent_count users!";
                header("Location: admin_announcements.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to send announcement: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'delete_announcement') {
        $announcement_id = $_POST['announcement_id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        
        $_SESSION['success_message'] = "Announcement deleted successfully!";
        header("Location: admin_announcements.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all teams for target dropdown
$stmt = $pdo->query("SELECT id, team_name FROM teams ORDER BY team_name");
$teams = $stmt->fetchAll();

// Get announcement history
$stmt = $pdo->query("SELECT a.*, u.name as sent_by_name 
                     FROM announcements a 
                     LEFT JOIN users u ON a.sent_by = u.id 
                     ORDER BY a.created_at DESC");
$announcements = $stmt->fetchAll();

// Get statistics
$total_sent = 0;
foreach ($announcements as $ann) {
    $total_sent += $ann['sent_to_count'];
}
$total_announcements = count($announcements);
$last_announcement = !empty($announcements) ? $announcements[0] : null;

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - LeaderDesk Admin</title>
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

        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .compose-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 24px;
        }

        .compose-card h2 {
            font-size: 18px;
            margin-bottom: 20px;
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
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 14px;
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

        .btn {
            padding: 12px 24px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
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
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .announcement-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 20px;
            transition: all 0.2s;
        }

        .announcement-card:hover {
            box-shadow: 0 5px 15px -5px rgba(0,0,0,0.05);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .announcement-title {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .priority-badge {
            padding: 3px 8px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 600;
        }

        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-normal {
            background: #e0f2fe;
            color: #0369a1;
        }

        .priority-low {
            background: #f1f5f9;
            color: #475569;
        }

        .announcement-meta {
            display: flex;
            gap: 16px;
            color: #666;
            font-size: 12px;
        }

        .announcement-message {
            color: #4a4a4a;
            line-height: 1.6;
            margin-bottom: 12px;
            padding: 12px;
            background: #fafafa;
            border-radius: 10px;
            font-size: 14px;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #888;
        }

        .target-badge {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 100px;
            font-size: 11px;
        }

        .btn-icon {
            padding: 6px 12px;
            border: 1px solid #eaeaea;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-icon:hover {
            background: #f5f5f5;
        }

        .btn-icon.delete:hover {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #888;
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
                padding: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
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
                <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
                <li><a href="admin_teams.php">👥 Teams</a></li>
                <li><a href="admin_users.php">👤 Users</a></li>
                <li><a href="admin_subscriptions.php">💳 Subscriptions</a></li>
                <li><a href="admin_announcements.php" class="active">📢 Announcements</a></li>
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
                    <h1>Announcements</h1>
                    <p>Send and manage platform announcements</p>
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

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_announcements; ?></div>
                    <div class="stat-label">Total Announcements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($total_sent); ?></div>
                    <div class="stat-label">Total Recipients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $last_announcement ? date('M d', strtotime($last_announcement['created_at'])) : '-'; ?></div>
                    <div class="stat-label">Last Sent</div>
                </div>
            </div>

            <!-- Compose New -->
            <div class="compose-card">
                <h2>Compose New Announcement</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="send_announcement">
                    
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-input" required placeholder="e.g., Platform Maintenance">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-textarea" required placeholder="Enter your message..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Send To</label>
                            <select name="target" class="form-select" required>
                                <option value="all">All Users</option>
                                <option value="leaders">Team Leaders Only</option>
                                <option value="members">Members Only</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="team_<?php echo $team['id']; ?>">Team: <?php echo htmlspecialchars($team['team_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send Announcement</button>
                </form>
            </div>

            <!-- History -->
            <h2 style="font-size: 16px; margin-bottom: 16px;">Announcement History</h2>
            
            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <span>📢</span>
                    <h3>No announcements yet</h3>
                    <p>Your sent announcements will appear here</p>
                </div>
            <?php else: ?>
                <div class="announcements-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <div class="announcement-title">
                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                    <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                        <?php echo ucfirst($announcement['priority']); ?>
                                    </span>
                                </div>
                                <div class="announcement-meta">
                                    <span><?php echo date('M d, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                    <span>By <?php echo htmlspecialchars($announcement['sent_by_name'] ?? 'System'); ?></span>
                                </div>
                            </div>
                            
                            <div class="announcement-message">
                                <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                            </div>
                            
                            <div class="announcement-footer">
                                <div>
                                    <span class="target-badge">
                                        Target: <?php 
                                            $target = $announcement['target'];
                                            if ($target == 'all') echo 'All Users';
                                            elseif ($target == 'leaders') echo 'Team Leaders';
                                            elseif ($target == 'members') echo 'Members';
                                            elseif (strpos($target, 'team_') === 0) echo 'Specific Team';
                                            else echo ucfirst($target);
                                        ?>
                                    </span>
                                    <span style="margin-left: 12px;">👥 <?php echo $announcement['sent_to_count']; ?> recipients</span>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('Delete this announcement?')">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn-icon delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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