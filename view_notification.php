<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notification_id) {
    header("Location: notifications.php");
    exit();
}

// Get notification details
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
$stmt->execute([$notification_id, $current_user['id']]);
$notification = $stmt->fetch();

if (!$notification) {
    header("Location: notifications.php");
    exit();
}

// Mark as read
if (!$notification['is_read']) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$notification_id]);
}

$page_title = $notification['title'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($notification['title']); ?> - LeaderDesk</title>
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

        .notification-detail-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .notification-header {
            margin-bottom: 24px;
        }

        .notification-type {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .type-announcement {
            background: #fef3c7;
            color: #92400e;
        }

        .type-training {
            background: #dbeafe;
            color: #1e40af;
        }

        .type-task {
            background: #e0f2fe;
            color: #0369a1;
        }

        .type-member {
            background: #ecfdf5;
            color: #065f46;
        }

        .type-event {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .type-target {
            background: #fee2e2;
            color: #991b1b;
        }

        .type-system {
            background: #f1f5f9;
            color: #475569;
        }

        .notification-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a1a;
        }

        .notification-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 14px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
        }

        .notification-content {
            font-size: 16px;
            line-height: 1.8;
            color: #333;
            margin-bottom: 32px;
            padding: 20px;
            background: #fafafa;
            border-radius: 12px;
        }

        .notification-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
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
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
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

            .notification-detail-container {
                padding: 24px;
            }

            .notification-title {
                font-size: 22px;
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
                <li class="nav-item active">
                    <a href="notifications.php">
                        <span class="nav-icon">🔔</span>
                        Notifications
                        <span class="nav-badge" id="sideNotificationCount"></span>
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
                    <h1>Notification Details</h1>
                    <p>View full notification content</p>
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

            <!-- Notification Detail -->
            <div class="notification-detail-container">
                <div class="notification-header">
                    <span class="notification-type type-<?php echo $notification['type']; ?>">
                        <?php echo ucfirst($notification['type']); ?>
                    </span>
                    <h1 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h1>
                    <div class="notification-meta">
                        <span>📅 <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                        <span>📌 <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?></span>
                    </div>
                </div>
                
                <div class="notification-content">
                    <?php 
                    if (!empty($notification['content'])) {
                        echo nl2br(htmlspecialchars($notification['content']));
                    } else {
                        echo nl2br(htmlspecialchars($notification['message']));
                    }
                    ?>
                </div>
                
                <div class="notification-actions">
                    <a href="notifications.php" class="btn btn-outline">← Back to Notifications</a>
                    
                    <?php if (!empty($notification['link']) && $notification['link'] != '#'): ?>
                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-primary">
                            View Related Content →
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!$notification['is_read']): ?>
                        <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="btn btn-outline">
                            Mark as Read
                        </a>
                    <?php endif; ?>
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
    </script>
</body>
</html>