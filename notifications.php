<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Notifications";

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $current_user['id']]);
    header("Location: notifications.php");
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND team_id = ?");
    $stmt->execute([$current_user['id'], $current_user['team_id']]);
    header("Location: notifications.php");
    exit();
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';

// Get all notifications for the user
$query = "SELECT * FROM notifications WHERE user_id = ? AND team_id = ?";
if ($filter == 'unread') {
    $query .= " AND is_read = 0";
} elseif ($filter == 'announcements') {
    $query .= " AND type = 'announcement'";
} elseif ($filter == 'training') {
    $query .= " AND type = 'training'";
} elseif ($filter == 'task') {
    $query .= " AND type = 'task'";
} elseif ($filter == 'member') {
    $query .= " AND type = 'member'";
} elseif ($filter == 'event') {
    $query .= " AND type = 'event'";
} elseif ($filter == 'target') {
    $query .= " AND type = 'target'";
} elseif ($filter == 'system') {
    $query .= " AND type = 'system'";
}
$query .= " ORDER BY 
    CASE WHEN is_read = 0 THEN 0 ELSE 1 END,
    created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$current_user['id'], $current_user['team_id']]);
$notifications = $stmt->fetchAll();

// Get counts for filters
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN type = 'announcement' THEN 1 ELSE 0 END) as announcements,
    SUM(CASE WHEN type = 'training' THEN 1 ELSE 0 END) as training,
    SUM(CASE WHEN type = 'task' THEN 1 ELSE 0 END) as task,
    SUM(CASE WHEN type = 'member' THEN 1 ELSE 0 END) as member,
    SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) as event,
    SUM(CASE WHEN type = 'target' THEN 1 ELSE 0 END) as target,
    SUM(CASE WHEN type = 'system' THEN 1 ELSE 0 END) as system
 FROM notifications WHERE user_id = ? AND team_id = ?");
$stmt->execute([$current_user['id'], $current_user['team_id']]);
$counts = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - LeaderDesk</title>
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

        .upgrade-btn.pending {
            background: #6b7280;
            cursor: not-allowed;
            pointer-events: none;
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

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eaeaea;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 100px;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-tab:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        .filter-tab.active {
            background: #1a1a1a;
            color: white;
        }

        .filter-tab .count {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 100px;
            font-size: 11px;
        }

        .filter-tab.active .count {
            background: rgba(255,255,255,0.3);
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .notification-card {
            display: flex;
            gap: 20px;
            padding: 24px;
            background: white;
            border: 1px solid #eaeaea;
            border-radius: 20px;
            transition: all 0.2s;
            position: relative;
            animation: fadeIn 0.3s ease-out;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .notification-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            transform: translateY(-2px);
            border-color: #1a1a1a;
        }

        .notification-card.unread {
            background: #fafafa;
            border-left: 4px solid #1a1a1a;
        }

        .notification-icon {
            width: 48px;
            height: 48px;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .notification-icon.announcement {
            background: #fef3c7;
            color: #92400e;
        }

        .notification-icon.training {
            background: #dbeafe;
            color: #1e40af;
        }

        .notification-icon.task {
            background: #e0f2fe;
            color: #0369a1;
        }

        .notification-icon.member {
            background: #ecfdf5;
            color: #065f46;
        }

        .notification-icon.event {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .notification-icon.target {
            background: #fee2e2;
            color: #991b1b;
        }

        .notification-icon.system {
            background: #f1f5f9;
            color: #475569;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notification-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .notification-time {
            font-size: 12px;
            color: #888;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 100px;
        }

        .notification-card.unread .notification-time {
            background: #eaeaea;
            color: #666;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notification-footer {
            display: flex;
            gap: 16px;
            font-size: 12px;
        }

        .notification-type {
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-date {
            color: #999;
        }

        .mark-read-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #eaeaea;
            border-radius: 50%;
            color: #1a1a1a;
            font-size: 18px;
            text-decoration: none;
            position: absolute;
            top: 24px;
            right: 24px;
            transition: all 0.2s;
            z-index: 10;
        }

        .mark-read-btn:hover {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
        }

        .empty-state span {
            font-size: 64px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .empty-state p {
            color: #888;
            font-size: 16px;
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

            .notification-card {
                flex-direction: column;
                padding-right: 60px;
            }
            
            .mark-read-btn {
                top: 20px;
                right: 20px;
            }
            
            .filter-tabs {
                flex-wrap: wrap;
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
                        <span class="nav-badge" id="sideNotificationCount"><?php echo $counts['unread'] ?: ''; ?></span>
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
                    <h1>Notifications</h1>
                    <p>Stay updated with your team activities</p>
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

            <!-- Header Actions -->
            <div class="header-actions" style="margin-bottom: 20px;">
                <?php if ($counts['unread'] > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-outline">Mark All as Read</a>
                <?php endif; ?>
            </div>

            <!-- Filter Tabs with Counts -->
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    All <span class="count"><?php echo $counts['total']; ?></span>
                </a>
                <a href="?filter=unread" class="filter-tab <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                    Unread <span class="count"><?php echo $counts['unread']; ?></span>
                </a>
                <a href="?filter=announcements" class="filter-tab <?php echo $filter == 'announcements' ? 'active' : ''; ?>">
                    Announcements <span class="count"><?php echo $counts['announcements']; ?></span>
                </a>
                <a href="?filter=training" class="filter-tab <?php echo $filter == 'training' ? 'active' : ''; ?>">
                    Training <span class="count"><?php echo $counts['training']; ?></span>
                </a>
                <a href="?filter=task" class="filter-tab <?php echo $filter == 'task' ? 'active' : ''; ?>">
                    Tasks <span class="count"><?php echo $counts['task']; ?></span>
                </a>
                <a href="?filter=member" class="filter-tab <?php echo $filter == 'member' ? 'active' : ''; ?>">
                    Members <span class="count"><?php echo $counts['member']; ?></span>
                </a>
                <a href="?filter=event" class="filter-tab <?php echo $filter == 'event' ? 'active' : ''; ?>">
                    Events <span class="count"><?php echo $counts['event']; ?></span>
                </a>
                <a href="?filter=target" class="filter-tab <?php echo $filter == 'target' ? 'active' : ''; ?>">
                    Targets <span class="count"><?php echo $counts['target']; ?></span>
                </a>
                <a href="?filter=system" class="filter-tab <?php echo $filter == 'system' ? 'active' : ''; ?>">
                    System <span class="count"><?php echo $counts['system']; ?></span>
                </a>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <span>📬</span>
                        <h3>No notifications</h3>
                        <p>You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): 
                        $icon = [
                            'task' => '✅',
                            'member' => '👤',
                            'event' => '📅',
                            'training' => '📚',
                            'target' => '🎯',
                            'announcement' => '📢',
                            'system' => '🔔'
                        ][$notification['type']] ?? '🔔';
                    ?>
                        <a href="view_notification.php?id=<?php echo $notification['id']; ?>" class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            
                            <div class="notification-icon <?php echo $notification['type']; ?>">
                                <?php echo $icon; ?>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                                    <span class="notification-time"><?php echo time_elapsed_string($notification['created_at']); ?></span>
                                </div>
                                
                                <p class="notification-message">
                                    <?php 
                                    if (!empty($notification['content'])) {
                                        echo htmlspecialchars(substr($notification['content'], 0, 150)) . (strlen($notification['content']) > 150 ? '...' : '');
                                    } else {
                                        echo htmlspecialchars(substr($notification['message'], 0, 150)) . (strlen($notification['message']) > 150 ? '...' : '');
                                    }
                                    ?>
                                </p>
                                
                                <div class="notification-footer">
                                    <span class="notification-type"><?php echo ucfirst($notification['type']); ?></span>
                                    <span class="notification-date"><?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!$notification['is_read']): ?>
                                <div class="mark-read-btn" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='?mark_read=<?php echo $notification['id']; ?>'">✓</div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    </script>
</body>
</html>