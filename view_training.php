<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$training_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$training_id) {
    header("Location: training.php");
    exit();
}

// Get training details
$stmt = $pdo->prepare("
    SELECT t.*, 
           u.name as uploader_name,
           u2.name as updater_name
    FROM trainings t 
    LEFT JOIN users u ON t.uploaded_by = u.id 
    LEFT JOIN users u2 ON t.updated_by = u2.id
    WHERE t.id = ? AND t.team_id = ?
");
$stmt->execute([$training_id, $current_user['team_id']]);
$training = $stmt->fetch();

if (!$training) {
    header("Location: training.php");
    exit();
}

$page_title = $training['title'];
$base_url = '/leaderdesk'; // Change this if your site is in a different folder
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($training['title']); ?> - LeaderDesk</title>
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

        .training-detail-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
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

        .detail-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.2;
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-download {
            background: #10b981;
            color: white;
            padding: 14px 32px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-download:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }

        .btn-view {
            display: inline-block;
            padding: 14px 32px;
            background: #1a1a1a;
            color: white;
            text-decoration: none;
            border-radius: 100px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .training-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            background: #f5f5f5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
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

        .training-description {
            margin-bottom: 40px;
        }

        .training-description h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1a1a1a;
        }

        .description-content {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 24px;
            line-height: 1.8;
            color: #333;
        }

        .training-content-section {
            margin-bottom: 40px;
        }

        .training-content-section h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
        }

        .file-info-box {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            border: 2px solid #10b981;
        }

        .file-icon-large {
            font-size: 80px;
            margin-bottom: 20px;
            color: #10b981;
        }

        .file-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .file-meta {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .download-button-container {
            margin-top: 30px;
        }

        .link-info-box {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
        }

        .link-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .link-url {
            color: #1a1a1a;
            word-break: break-all;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .training-footer {
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
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
            min-height: 120px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
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

            .training-detail-container {
                padding: 24px;
            }
            
            .detail-header {
                flex-direction: column;
            }
            
            .detail-header h1 {
                font-size: 24px;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                flex: 1;
                text-align: center;
            }
            
            .training-footer {
                flex-direction: column;
            }
            
            .training-footer .btn,
            .training-footer .btn-download {
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
                <li class="nav-item active">
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
                    <h1>Training Details</h1>
                    <p><?php echo htmlspecialchars($training['title']); ?></p>
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

            <!-- Training Detail Content -->
            <div class="training-detail-container">
                <!-- Header with actions -->
                <div class="detail-header">
                    <div class="header-left">
                        <a href="training.php" class="back-btn">← Back to Training</a>
                        <h1><?php echo htmlspecialchars($training['title']); ?></h1>
                    </div>
                    
                    <?php if ($current_user['role'] == 'team_leader' || $training['uploaded_by'] == $current_user['id']): ?>
                        <div class="header-actions">
                            <button class="btn btn-outline" onclick="openEditModal()">✏️ Edit</button>
                            <button class="btn btn-danger" onclick="deleteTraining(<?php echo $training['id']; ?>)">🗑️ Delete</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Training Meta Info -->
                <div class="training-meta">
                    <div class="meta-item">
                        <span class="meta-label">Category</span>
                        <span class="meta-value">
                            <?php 
                            $categories = [
                                'getting_started' => 'Getting Started',
                                'product' => 'Product Training',
                                'recruitment' => 'Recruitment',
                                'leadership' => 'Leadership'
                            ];
                            echo $categories[$training['category']] ?? $training['category'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Type</span>
                        <span class="meta-value">
                            <?php 
                            if ($training['content_type'] == 'video_link') echo '🎥 Video Link';
                            elseif ($training['content_type'] == 'file') echo '📁 File';
                            elseif ($training['content_type'] == 'link') echo '🔗 Web Link';
                            else echo '⚠️ Not specified';
                            ?>
                        </span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Uploaded by</span>
                        <span class="meta-value"><?php echo htmlspecialchars($training['uploader_name']); ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Date</span>
                        <span class="meta-value"><?php echo date('F j, Y g:i A', strtotime($training['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($training['updated_at']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Last updated</span>
                            <span class="meta-value">
                                <?php echo date('F j, Y g:i A', strtotime($training['updated_at'])); ?>
                                <?php if ($training['updater_name']): ?>
                                    by <?php echo htmlspecialchars($training['updater_name']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if (!empty($training['description'])): ?>
                    <div class="training-description">
                        <h2>Description</h2>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($training['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Display -->
                <div class="training-content-section">
                    <h2>Content</h2>
                    
                    <?php if ($training['content_type'] == 'file'): ?>
                        <?php if (!empty($training['file_path'])): ?>
                            <?php
                            $file_name = basename($training['file_path']);
                            $file_exists = file_exists($training['file_path']);
                            $file_size = $file_exists ? filesize($training['file_path']) : 0;
                            $file_size_formatted = $file_size > 0 ? round($file_size / 1024 / 1024, 2) . ' MB' : 'Unknown';
                            $ext = strtolower(pathinfo($training['file_path'], PATHINFO_EXTENSION));
                            
                            $file_icon = '📄';
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $file_icon = '🖼️';
                            elseif ($ext == 'pdf') $file_icon = '📕';
                            elseif (in_array($ext, ['doc', 'docx'])) $file_icon = '📘';
                            elseif (in_array($ext, ['ppt', 'pptx'])) $file_icon = '📊';
                            elseif (in_array($ext, ['mp4', 'webm', 'ogg'])) $file_icon = '🎥';
                            ?>
                            
                            <div class="file-info-box">
                                <div class="file-icon-large"><?php echo $file_icon; ?></div>
                                <div class="file-name"><?php echo htmlspecialchars($file_name); ?></div>
                                <div class="file-meta">
                                    Type: <?php echo strtoupper($ext); ?> • 
                                    Size: <?php echo $file_size_formatted; ?>
                                </div>
                                
                                <div class="download-button-container">
                                    <a href="/leaderdesk/<?php echo htmlspecialchars($training['file_path']); ?>" download="<?php echo htmlspecialchars($file_name); ?>" class="btn-download">
                                        ⬇️ Download File
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="file-info-box">
                                <div class="file-icon-large">❓</div>
                                <div class="file-name">No file attached</div>
                                <div class="file-meta">This training material has no file.</div>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($training['content_type'] == 'video_link' && !empty($training['content_url'])): ?>
                        <div class="link-info-box">
                            <div class="link-icon">🎥</div>
                            <h3>Video Link</h3>
                            <div class="link-url">
                                <a href="<?php echo htmlspecialchars($training['content_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($training['content_url']); ?>
                                </a>
                            </div>
                            <a href="<?php echo htmlspecialchars($training['content_url']); ?>" target="_blank" class="btn-view">
                                Watch Video
                            </a>
                        </div>
                        
                    <?php elseif ($training['content_type'] == 'link' && !empty($training['content_url'])): ?>
                        <div class="link-info-box">
                            <div class="link-icon">🔗</div>
                            <h3>Web Link</h3>
                            <div class="link-url">
                                <a href="<?php echo htmlspecialchars($training['content_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($training['content_url']); ?>
                                </a>
                            </div>
                            <a href="<?php echo htmlspecialchars($training['content_url']); ?>" target="_blank" class="btn-view">
                                Open Link
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <div class="file-info-box">
                            <div class="file-icon-large">⚠️</div>
                            <div class="file-name">Content type not specified</div>
                            <div class="file-meta">Please contact your team leader to fix this training material.</div>
                            
                            <?php if (!empty($training['file_path'])): ?>
                                <div style="margin-top: 20px;">
                                    <p>But there is a file attached! Try this direct link:</p>
                                    <a href="/leaderdesk/<?php echo htmlspecialchars($training['file_path']); ?>" download class="btn-download">
                                        ⬇️ Download File Directly
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="training-footer">
                    <a href="training.php" class="btn btn-outline">← Back to Training</a>
                    
                    <?php if ($training['content_type'] == 'file' && !empty($training['file_path'])): ?>
                        <a href="/leaderdesk/<?php echo htmlspecialchars($training['file_path']); ?>" download="<?php echo basename($training['file_path']); ?>" class="btn-download">
                            ⬇️ Download File
                        </a>
                    <?php elseif (!empty($training['file_path'])): ?>
                        <a href="/leaderdesk/<?php echo htmlspecialchars($training['file_path']); ?>" download="<?php echo basename($training['file_path']); ?>" class="btn-download">
                            ⬇️ Download File
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Training Modal -->
    <div id="editTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Training Material</h2>
                <button class="modal-close" onclick="closeModal('editTrainingModal')">&times;</button>
            </div>
            
            <form method="POST" action="training.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_training">
                <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($training['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="4"><?php echo htmlspecialchars($training['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="getting_started" <?php echo $training['category'] == 'getting_started' ? 'selected' : ''; ?>>Getting Started</option>
                        <option value="product" <?php echo $training['category'] == 'product' ? 'selected' : ''; ?>>Product Training</option>
                        <option value="recruitment" <?php echo $training['category'] == 'recruitment' ? 'selected' : ''; ?>>Recruitment</option>
                        <option value="leadership" <?php echo $training['category'] == 'leadership' ? 'selected' : ''; ?>>Leadership</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content Type</label>
                    <select name="content_type" class="form-select" required>
                        <option value="file" <?php echo $training['content_type'] == 'file' ? 'selected' : ''; ?>>File</option>
                        <option value="video_link" <?php echo $training['content_type'] == 'video_link' ? 'selected' : ''; ?>>Video Link</option>
                        <option value="link" <?php echo $training['content_type'] == 'link' ? 'selected' : ''; ?>>Web Link</option>
                    </select>
                </div>
                
                <?php if ($training['content_type'] == 'file'): ?>
                    <div class="form-group">
                        <label class="form-label">Replace File (Optional)</label>
                        <input type="file" name="training_file" class="form-input">
                        <small style="color: #666;">Leave empty to keep current file</small>
                    </div>
                <?php endif; ?>
                
                <?php if ($training['content_type'] == 'video_link' || $training['content_type'] == 'link'): ?>
                    <div class="form-group">
                        <label class="form-label">URL</label>
                        <input type="url" name="content_url" class="form-input" value="<?php echo htmlspecialchars($training['content_url']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editTrainingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Training</h2>
                <button class="modal-close" onclick="closeModal('deleteTrainingModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this training material? This action cannot be undone.</p>
            
            <form method="POST" action="training.php">
                <input type="hidden" name="action" value="delete_training">
                <input type="hidden" name="training_id" value="<?php echo $training['id']; ?>">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteTrainingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete Training</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal() {
            document.getElementById('editTrainingModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function deleteTraining(trainingId) {
            document.getElementById('deleteTrainingModal').classList.add('show');
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

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>