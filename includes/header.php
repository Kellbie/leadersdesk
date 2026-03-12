<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>LeaderDesk - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo isset($current_user['primary_color']) ? $current_user['primary_color'] : '#1a1a1a'; ?>;
        }
        
        /* Header Styles */
        .main-header {
            background: white;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eaeaea;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #1a1a1a;
            display: block;
            padding: 0.5rem;
            line-height: 1;
        }

        @media (min-width: 768px) {
            .menu-toggle {
                display: none;
            }
        }

        .logo {
            font-weight: 700;
            font-size: 1.25rem;
        }

        .logo-text {
            color: #1a1a1a;
        }

        .logo img {
            height: 32px;
            width: auto;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }

        /* Upgrade Button */
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

        .upgrade-btn.pending:hover {
            transform: none;
        }

        /* Notifications */
        .notifications {
            position: relative;
        }

        .notifications-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
            position: relative;
        }

        .notifications-link:hover {
            background: #f5f5f5;
        }

        .notification-icon {
            font-size: 1.25rem;
        }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #dc2626;
            color: white;
            font-size: 0.7rem;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            min-width: 18px;
            text-align: center;
        }

        /* Profile */
        .profile-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .profile-link:hover .user-profile {
            background: #eaeaea;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            border-radius: 100px;
            background: #f5f5f5;
            transition: background 0.2s;
            cursor: pointer;
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
            font-size: 16px;
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
                color: #1a1a1a;
            }
            
            .user-role {
                font-size: 12px;
                color: #666;
            }
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
            .upgrade-btn span:last-child {
                display: none;
            }
            .upgrade-btn {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php if(isset($current_user)): ?>
        <header class="main-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <div class="logo">
                    <?php if(!empty($current_user['logo_url'])): ?>
                        <img src="<?php echo $current_user['logo_url']; ?>" alt="Team Logo">
                    <?php else: ?>
                        <span class="logo-text">LeaderDesk</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="header-right">
                <!-- Upgrade Button for Members -->
                <?php if ($current_user['role'] == 'member'): ?>
                    <?php
                    // Check if upgrade already requested
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
                    <a href="notifications.php" class="notifications-link">
                        <span class="notification-icon">🔔</span>
                        <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                    </a>
                </div>
                
                <!-- Profile -->
                <a href="profile.php" class="profile-link">
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
        </header>
        
        <?php include 'includes/navigation.php'; ?>
        <main class="main-content">
    <?php endif; ?>