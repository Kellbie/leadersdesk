<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$share_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$share_id) {
    header("Location: prospects.php");
    exit();
}

// Get shared file details
$stmt = $pdo->prepare("
    SELECT psf.*, 
           p.name as prospect_name,
           p.id as prospect_id,
           p.user_id as prospect_owner_id,
           u.name as shared_by_name,
           u.id as shared_by_id
    FROM prospect_shared_files psf
    JOIN prospects p ON psf.prospect_id = p.id
    JOIN users u ON psf.shared_by = u.id
    WHERE psf.id = ? AND p.team_id = ?
");
$stmt->execute([$share_id, $current_user['team_id']]);
$share = $stmt->fetch();

if (!$share) {
    header("Location: prospects.php");
    exit();
}

// Check if user has permission to view
if ($current_user['role'] != 'team_leader' && 
    $share['prospect_owner_id'] != $current_user['id'] && 
    $share['shared_by_id'] != $current_user['id']) {
    header("Location: prospects.php");
    exit();
}

// Mark as viewed
if (!$share['viewed_at'] && $share['prospect_owner_id'] == $current_user['id']) {
    $stmt = $pdo->prepare("UPDATE prospect_shared_files SET viewed_at = NOW() WHERE id = ?");
    $stmt->execute([$share_id]);
}

$page_title = "Shared File - " . $share['file_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($share['file_name']); ?> - LeaderDesk</title>
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

        .file-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .file-card {
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .file-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .file-header-left {
            flex: 1;
        }

        .back-btn {
            display: inline-block;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .back-btn:hover {
            color: #1a1a1a;
        }

        .file-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            word-break: break-word;
        }

        .file-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            background: #f5f5f5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .message-box {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .message-box h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #92400e;
        }

        .message-box p {
            color: #92400e;
            font-size: 15px;
            line-height: 1.6;
        }

        .file-preview {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 32px;
            text-align: center;
        }

        .file-icon-large {
            font-size: 80px;
            margin-bottom: 20px;
            color: #1a1a1a;
        }

        .file-name-large {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .file-size {
            color: #666;
            margin-bottom: 24px;
        }

        .download-btn {
            display: inline-block;
            padding: 16px 40px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 100px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .download-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }

        .file-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
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

        .view-stats {
            font-size: 13px;
            color: #888;
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

            .file-card {
                padding: 24px;
            }

            .file-header h1 {
                font-size: 22px;
            }

            .file-footer {
                flex-direction: column;
            }

            .file-footer .btn {
                width: 100%;
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
                    <h1>Shared File</h1>
                    <p>View and download shared file</p>
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

            <div class="file-container">
                <div class="file-card">
                    <div class="file-header">
                        <div class="file-header-left">
                            <a href="prospects.php" class="back-btn">← Back to Prospects</a>
                            <h1><?php echo htmlspecialchars($share['file_name']); ?></h1>
                        </div>
                    </div>

                    <div class="file-meta">
                        <div class="meta-item">
                            <span class="meta-label">Shared with</span>
                            <span class="meta-value"><?php echo htmlspecialchars($share['prospect_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Shared by</span>
                            <span class="meta-value"><?php echo htmlspecialchars($share['shared_by_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Shared on</span>
                            <span class="meta-value"><?php echo date('M d, Y g:i A', strtotime($share['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">File size</span>
                            <span class="meta-value"><?php echo round($share['file_size'] / 1024 / 1024, 2); ?> MB</span>
                        </div>
                    </div>

                    <?php if ($share['message']): ?>
                        <div class="message-box">
                            <h3>📝 Message</h3>
                            <p><?php echo nl2br(htmlspecialchars($share['message'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="file-preview">
                        <?php
                        $file_ext = strtolower(pathinfo($share['file_name'], PATHINFO_EXTENSION));
                        $icon = '📄';
                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $icon = '🖼️';
                        } elseif ($file_ext == 'pdf') {
                            $icon = '📕';
                        } elseif (in_array($file_ext, ['doc', 'docx'])) {
                            $icon = '📘';
                        } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                            $icon = '📊';
                        } elseif (in_array($file_ext, ['mp4', 'webm', 'ogg'])) {
                            $icon = '🎥';
                        } elseif (in_array($file_ext, ['mp3', 'wav'])) {
                            $icon = '🎵';
                        } elseif (in_array($file_ext, ['zip', 'rar', '7z'])) {
                            $icon = '🗜️';
                        }
                        ?>
                        
                        <div class="file-icon-large"><?php echo $icon; ?></div>
                        <div class="file-name-large"><?php echo htmlspecialchars($share['file_name']); ?></div>
                        <div class="file-size"><?php echo round($share['file_size'] / 1024 / 1024, 2); ?> MB • <?php echo strtoupper($file_ext); ?> file</div>
                        
                        <a href="<?php echo htmlspecialchars($share['file_path']); ?>" download="<?php echo htmlspecialchars($share['file_name']); ?>" class="download-btn">
                            ⬇️ Download File
                        </a>
                    </div>

                    <div class="file-footer">
                        <div class="view-stats">
                            <?php if ($share['viewed_at']): ?>
                                <span>👁️ Viewed on <?php echo date('M d, Y g:i A', strtotime($share['viewed_at'])); ?></span>
                            <?php else: ?>
                                <span>👁️ Not viewed yet</span>
                            <?php endif; ?>
                            
                            <?php if ($share['downloaded_at']): ?>
                                <span style="margin-left: 16px;">⬇️ Downloaded on <?php echo date('M d, Y g:i A', strtotime($share['downloaded_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="prospects.php" class="btn btn-outline">Back to Prospects</a>
                    </div>
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