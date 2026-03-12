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

$page_title = "Settings";
$message = '';
$error = '';

// Create settings table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create platform_status table for disable feature
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_disabled TINYINT DEFAULT 0,
        disabled_message TEXT,
        updated_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default platform status if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM platform_status");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO platform_status (is_disabled, disabled_message) VALUES (0, '')");
    }
} catch (Exception $e) {
    // Tables might already exist
}

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_settings') {
        $site_name = $_POST['site_name'] ?? 'LeaderDesk';
        $support_email = $_POST['support_email'] ?? '';
        $trial_days = (int)($_POST['trial_days'] ?? 60);
        $monthly_price = (float)($_POST['monthly_price'] ?? 29);
        $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
        $require_verification = isset($_POST['require_verification']) ? 1 : 0;
        
        // Save settings to database
        $settings = [
            'site_name' => $site_name,
            'support_email' => $support_email,
            'trial_days' => $trial_days,
            'monthly_price' => $monthly_price,
            'allow_registration' => $allow_registration,
            'require_verification' => $require_verification
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        $_SESSION['success_message'] = "Settings updated successfully!";
        header("Location: admin_settings.php");
        exit();
        
    } elseif ($_POST['action'] == 'clear_cache') {
        // Clear system cache
        $cache_cleared = 0;
        
        // Clear compiled template cache if exists
        $cache_dir = __DIR__ . '/cache';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cache_cleared++;
                }
            }
        }
        
        // Clear session temp data (only old sessions)
        $temp_dir = ini_get('session.save_path');
        if ($temp_dir && is_dir($temp_dir)) {
            $files = glob($temp_dir . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file) && time() - filemtime($file) > 86400) { // older than 24 hours
                    unlink($file);
                }
            }
        }
        
        $_SESSION['success_message'] = "Cache cleared successfully! ($cache_cleared files removed)";
        header("Location: admin_settings.php");
        exit();
        
    } elseif ($_POST['action'] == 'run_maintenance') {
        // Run maintenance tasks
        $tasks_completed = [];
        
        try {
            // First, close any existing cursor by using fetchAll
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Now we can run OPTIMIZE on each table
            foreach ($tables as $table) {
                try {
                    // Use query() instead of exec() for OPTIMIZE
                    $pdo->query("OPTIMIZE TABLE `$table`")->fetchAll();
                    $tasks_completed[] = "✅ Optimized $table";
                } catch (Exception $e) {
                    $tasks_completed[] = "⚠️ Could not optimize $table";
                    error_log("Failed to optimize $table: " . $e->getMessage());
                }
            }
            
            // Clean up old activity logs (keep last 30 days)
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            $tasks_completed[] = "✅ Deleted $deleted old activity logs";
            
            // Clean up old notifications (keep last 60 days)
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY) AND is_read = 1");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            $tasks_completed[] = "✅ Deleted $deleted old notifications";
            
            // Clean up old shares (keep last 90 days)
            $stmt = $pdo->prepare("DELETE FROM training_shares WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            $tasks_completed[] = "✅ Deleted $deleted old training shares";
            
            $_SESSION['success_message'] = "Maintenance completed!<br>" . implode("<br>", $tasks_completed);
            
        } catch (Exception $e) {
            error_log("Maintenance error: " . $e->getMessage());
            $_SESSION['error_message'] = "Maintenance error: " . $e->getMessage();
        }
        
        header("Location: admin_settings.php");
        exit();
        
    } elseif ($_POST['action'] == 'backup_database') {
        // Create database backup
        $backup_dir = __DIR__ . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        try {
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $output = "-- LeaderDesk Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Host: " . DB_HOST . "\n";
            $output .= "-- Database: " . DB_NAME . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Get create table syntax
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch();
                $output .= "\n\n-- Table structure for table `$table`\n";
                $output .= "DROP TABLE IF EXISTS `$table`;\n";
                $output .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    $output .= "-- Dumping data for table `$table`\n";
                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $values = array_values($row);
                        
                        // Escape values
                        foreach ($values as &$value) {
                            if ($value === null) {
                                $value = 'NULL';
                            } else {
                                $value = "'" . addslashes($value) . "'";
                            }
                        }
                        
                        $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $output .= "\n";
                }
            }
            
            $output .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($filename, $output);
            
            $_SESSION['success_message'] = "Database backup created successfully! Filename: " . basename($filename);
            
        } catch (Exception $e) {
            error_log("Backup error: " . $e->getMessage());
            $_SESSION['error_message'] = "Backup failed: " . $e->getMessage();
        }
        
        header("Location: admin_settings.php");
        exit();
        
    } elseif ($_POST['action'] == 'disable_platform') {
        $disabled_message = $_POST['disabled_message'] ?? 'Platform is temporarily disabled for maintenance. Please check back later.';
        $disable = $_POST['disable'] ?? '1';
        
        $stmt = $pdo->prepare("UPDATE platform_status SET is_disabled = ?, disabled_message = ?, updated_by = ? WHERE id = 1");
        $stmt->execute([$disable, $disabled_message, $current_user['id']]);
        
        if ($disable == '1') {
            $_SESSION['success_message'] = "Platform has been disabled. Only super admins can still access.";
        } else {
            $_SESSION['success_message'] = "Platform has been re-enabled. All users can now access.";
        }
        header("Location: admin_settings.php");
        exit();
        
    } elseif ($_POST['action'] == 'reset_platform') {
        // This is a destructive action - requires double confirmation
        $confirm = $_POST['confirm'] ?? '';
        
        if ($confirm === 'RESET') {
            try {
                $pdo->beginTransaction();
                
                // Keep super admins and settings, but reset everything else
                $tables_to_reset = ['activity_logs', 'notifications', 'tasks', 'targets', 'events', 'event_attendance', 
                                   'trainings', 'tests', 'test_questions', 'prospects', 'user_badges', 'training_shares'];
                
                foreach ($tables_to_reset as $table) {
                    $pdo->exec("DELETE FROM `$table`");
                }
                
                // Reset teams but keep the team structure
                $pdo->exec("UPDATE teams SET subscription_status = 'trial', trial_start_date = CURDATE(), trial_end_date = DATE_ADD(CURDATE(), INTERVAL 60 DAY)");
                
                // Keep only super admins
                $pdo->exec("DELETE FROM users WHERE role NOT IN ('super_admin')");
                
                $pdo->commit();
                $_SESSION['success_message'] = "Platform has been reset successfully! All non-admin data cleared.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Reset error: " . $e->getMessage());
                $_SESSION['error_message'] = "Reset failed: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "Reset cancelled: Please type RESET to confirm";
        }
        header("Location: admin_settings.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Load settings from database
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get platform status
$stmt = $pdo->query("SELECT * FROM platform_status WHERE id = 1");
$platform_status = $stmt->fetch();

// Default values
$site_name = $settings['site_name'] ?? 'LeaderDesk';
$support_email = $settings['support_email'] ?? 'support@leaderdesk.com';
$trial_days = $settings['trial_days'] ?? 60;
$monthly_price = $settings['monthly_price'] ?? 29;
$allow_registration = isset($settings['allow_registration']) ? $settings['allow_registration'] : 1;
$require_verification = isset($settings['require_verification']) ? $settings['require_verification'] : 0;

// Get system info
$php_version = phpversion();
try {
    $mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch (Exception $e) {
    $mysql_version = 'Unknown';
}
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$upload_max_size = ini_get('upload_max_filesize');
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LeaderDesk Admin</title>
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

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 24px;
        }

        .settings-card.full-width {
            grid-column: 1 / -1;
        }

        .settings-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eaeaea;
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
        .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info-item {
            padding: 12px;
            background: #fafafa;
            border-radius: 8px;
        }

        .info-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }

        .info-value {
            font-weight: 600;
            font-size: 14px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .action-card {
            padding: 20px;
            background: #fafafa;
            border-radius: 12px;
            text-align: center;
        }

        .action-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .action-desc {
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
        }

        .danger-zone {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            padding: 20px;
        }

        .danger-zone h3 {
            color: #991b1b;
            margin-bottom: 4px;
        }

        .danger-zone p {
            color: #b91c1c;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-enabled {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-disabled {
            background: #fee2e2;
            color: #991b1b;
        }

        .backup-list {
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #eaeaea;
            border-radius: 8px;
        }

        .backup-item {
            padding: 10px 14px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-download {
            color: #1a1a1a;
            text-decoration: none;
            padding: 4px 8px;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
                <li><a href="admin_announcements.php">📢 Announcements</a></li>
                <li><a href="admin_settings.php" class="active">⚙️ Settings</a></li>
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
                    <h1>Platform Settings</h1>
                    <p>Configure system settings and preferences</p>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Platform Status -->
            <div class="settings-card full-width" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Platform Status</h2>
                    <span class="status-badge <?php echo $platform_status && $platform_status['is_disabled'] ? 'status-disabled' : 'status-enabled'; ?>">
                        <?php echo $platform_status && $platform_status['is_disabled'] ? '🚫 Disabled' : '✅ Enabled'; ?>
                    </span>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- General Settings -->
                <div class="settings-card">
                    <h2>General Settings</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars($site_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Support Email</label>
                            <input type="email" name="support_email" class="form-input" value="<?php echo htmlspecialchars($support_email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Trial Period (days)</label>
                            <input type="number" name="trial_days" class="form-input" value="<?php echo $trial_days; ?>" min="0" max="365">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Monthly Price ($)</label>
                            <input type="number" name="monthly_price" class="form-input" value="<?php echo $monthly_price; ?>" min="0" step="0.01">
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="allow_registration" id="allow_registration" <?php echo $allow_registration ? 'checked' : ''; ?>>
                            <label for="allow_registration">Allow new team registrations</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="require_verification" id="require_verification" <?php echo $require_verification ? 'checked' : ''; ?>>
                            <label for="require_verification">Require email verification</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>

                <!-- System Information -->
                <div class="settings-card">
                    <h2>System Information</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">PHP Version</div>
                            <div class="info-value"><?php echo $php_version; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">MySQL Version</div>
                            <div class="info-value"><?php echo $mysql_version; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Server</div>
                            <div class="info-value"><?php echo $server_software; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Upload Size</div>
                            <div class="info-value"><?php echo $upload_max_size; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Memory Limit</div>
                            <div class="info-value"><?php echo $memory_limit; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Max Execution</div>
                            <div class="info-value"><?php echo $max_execution_time; ?>s</div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance -->
                <div class="settings-card full-width">
                    <h2>Maintenance</h2>
                    
                    <div class="actions-grid">
                        <form method="POST" class="action-card">
                            <input type="hidden" name="action" value="clear_cache">
                            <div class="action-icon">🗑️</div>
                            <div class="action-title">Clear Cache</div>
                            <div class="action-desc">Clear temporary files</div>
                            <button type="submit" class="btn btn-outline">Clear Cache</button>
                        </form>
                        
                        <form method="POST" class="action-card">
                            <input type="hidden" name="action" value="run_maintenance">
                            <div class="action-icon">🔧</div>
                            <div class="action-title">Run Maintenance</div>
                            <div class="action-desc">Optimize database & clean up</div>
                            <button type="submit" class="btn btn-outline">Run Tasks</button>
                        </form>
                        
                        <form method="POST" class="action-card">
                            <input type="hidden" name="action" value="backup_database">
                            <div class="action-icon">💾</div>
                            <div class="action-title">Backup Database</div>
                            <div class="action-desc">Create SQL backup</div>
                            <button type="submit" class="btn btn-outline">Create Backup</button>
                        </form>
                    </div>

                    <!-- Recent Backups -->
                    <?php
                    $backup_dir = __DIR__ . '/backups';
                    if (is_dir($backup_dir)):
                        $backups = glob($backup_dir . '/*.sql');
                        rsort($backups);
                        if (!empty($backups)):
                    ?>
                        <div style="margin-top: 20px;">
                            <h3 style="font-size: 14px; margin-bottom: 10px;">Recent Backups</h3>
                            <div class="backup-list">
                                <?php foreach (array_slice($backups, 0, 5) as $backup): ?>
                                    <div class="backup-item">
                                        <span><?php echo basename($backup); ?></span>
                                        <span><?php echo file_exists($backup) ? round(filesize($backup) / 1024, 2) . ' KB' : '?'; ?></span>
                                        <a href="download_backup.php?file=<?php echo urlencode(basename($backup)); ?>" class="backup-download">⬇️</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>
                </div>

                <!-- Platform Control -->
                <div class="settings-card full-width">
                    <h2 style="color: #f59e0b;">Platform Control</h2>
                    
                    <div class="danger-zone" style="border-color: #f59e0b;">
                        <h3 style="color: #f59e0b;"><?php echo $platform_status && $platform_status['is_disabled'] ? 'Enable Platform' : 'Disable Platform'; ?></h3>
                        <p>
                            <?php if ($platform_status && $platform_status['is_disabled']): ?>
                                Currently disabled. Enable to allow all users to access.
                            <?php else: ?>
                                Disable platform access for all users except super admins.
                            <?php endif; ?>
                        </p>
                        
                        <form method="POST" style="display: flex; gap: 16px; align-items: flex-end;">
                            <input type="hidden" name="action" value="disable_platform">
                            <input type="hidden" name="disable" value="<?php echo $platform_status && $platform_status['is_disabled'] ? '0' : '1'; ?>">
                            
                            <div style="flex: 1;">
                                <label class="form-label">Message to Users (if disabling)</label>
                                <input type="text" name="disabled_message" class="form-input" 
                                       value="<?php echo htmlspecialchars($platform_status['disabled_message'] ?? 'Platform is temporarily disabled for maintenance.'); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <?php echo $platform_status && $platform_status['is_disabled'] ? 'Enable Platform' : 'Disable Platform'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="settings-card full-width">
                    <h2 style="color: #ef4444;">Danger Zone</h2>
                    
                    <div class="danger-zone">
                        <h3>Reset Platform</h3>
                        <p>This will delete all non-admin data. Type RESET to confirm.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="reset_platform">
                            
                            <div style="display: flex; gap: 16px; align-items: center;">
                                <input type="text" name="confirm" class="form-input" placeholder="Type RESET to confirm" style="flex: 1;" required>
                                <button type="submit" class="btn btn-danger">Reset Everything</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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