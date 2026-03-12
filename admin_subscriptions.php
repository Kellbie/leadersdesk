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

$page_title = "Subscription Management";
$message = '';
$error = '';

// Handle subscription updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_subscription') {
        $team_id = $_POST['team_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $trial_end = $_POST['trial_end'] ?? null;
        
        if ($team_id && $status) {
            if ($trial_end) {
                $stmt = $pdo->prepare("UPDATE teams SET subscription_status = ?, trial_end_date = ? WHERE id = ?");
                $stmt->execute([$status, $trial_end, $team_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE teams SET subscription_status = ? WHERE id = ?");
                $stmt->execute([$status, $team_id]);
            }
            
            $_SESSION['success_message'] = "Subscription updated successfully!";
            header("Location: admin_subscriptions.php");
            exit();
        }
    } elseif ($_POST['action'] == 'bulk_extend') {
        $days = $_POST['days'] ?? 30;
        $team_ids = $_POST['team_ids'] ?? [];
        
        if (!empty($team_ids)) {
            $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
            $params = array_merge([$days], $team_ids);
            
            $stmt = $pdo->prepare("UPDATE teams SET trial_end_date = DATE_ADD(trial_end_date, INTERVAL ? DAY) WHERE id IN ($placeholders) AND subscription_status = 'trial'");
            $stmt->execute($params);
            
            $count = $stmt->rowCount();
            $_SESSION['success_message'] = "Extended trial by $days days for $count teams!";
            header("Location: admin_subscriptions.php");
            exit();
        }
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all teams with subscription details - FIXED: Removed last_login reference
$stmt = $pdo->query("SELECT t.*, 
                     COUNT(u.id) as user_count
                     FROM teams t
                     LEFT JOIN users u ON t.id = u.team_id
                     GROUP BY t.id
                     ORDER BY 
                        CASE t.subscription_status
                            WHEN 'trial' THEN 1
                            WHEN 'active' THEN 2
                            WHEN 'expired' THEN 3
                            WHEN 'suspended' THEN 4
                        END,
                        t.trial_end_date ASC");
$teams = $stmt->fetchAll();

// Subscription statistics
$stats = [
    'total' => count($teams),
    'trial' => 0,
    'active' => 0,
    'expired' => 0,
    'suspended' => 0
];

foreach ($teams as $team) {
    switch ($team['subscription_status']) {
        case 'trial':
            $stats['trial']++;
            break;
        case 'active':
            $stats['active']++;
            break;
        case 'expired':
            $stats['expired']++;
            break;
        case 'suspended':
            $stats['suspended']++;
            break;
    }
}

// Calculate revenue (estimated)
$monthly_price = 29;
$estimated_revenue = $stats['active'] * $monthly_price;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Management - LeaderDesk Admin</title>
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
            width: 280px;
            background: #1a1a1a;
            color: white;
            padding: 32px 24px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .admin-logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid #333;
        }

        .admin-logo span {
            color: #ef4444;
            font-size: 12px;
            margin-left: 8px;
            background: #333;
            padding: 4px 8px;
            border-radius: 100px;
        }

        .admin-nav {
            list-style: none;
            flex: 1;
        }

        .admin-nav li {
            margin-bottom: 8px;
        }

        .admin-nav a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #999;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            gap: 12px;
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
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }

        .back-to-app a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ef4444;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-to-app a:hover {
            background: #333;
        }

        .admin-main {
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
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
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

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-sub {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        .revenue-card {
            background: linear-gradient(135deg, #1a1a1a, #333);
            color: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .revenue-info h2 {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .revenue-amount {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .revenue-period {
            opacity: 0.6;
            font-size: 14px;
        }

        .revenue-badge {
            background: rgba(255,255,255,0.1);
            padding: 12px 24px;
            border-radius: 100px;
            font-size: 16px;
        }

        .bulk-actions {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .bulk-actions h3 {
            font-size: 16px;
            font-weight: 600;
            min-width: 120px;
        }

        .bulk-controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
        }

        .bulk-select {
            padding: 10px 16px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 16px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .subscriptions-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .subscriptions-table th {
            background: #f5f5f5;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        .subscriptions-table td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .subscriptions-table tr:last-child td {
            border-bottom: none;
        }

        .subscriptions-table tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-trial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-active {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-suspended {
            background: #f1f5f9;
            color: #475569;
        }

        .trial-indicator {
            width: 100%;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }

        .trial-progress {
            height: 100%;
            background: #1a1a1a;
            border-radius: 2px;
        }

        .trial-progress.warning {
            background: #f59e0b;
        }

        .trial-progress.danger {
            background: #ef4444;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 12px;
            border: 1px solid #eaeaea;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: #f5f5f5;
        }

        .btn-icon.extend:hover {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .status-select {
            padding: 6px;
            border: 1px solid #eaeaea;
            border-radius: 6px;
            font-size: 12px;
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
            max-width: 500px;
            width: 90%;
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
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
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
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .revenue-card {
                flex-direction: column;
                text-align: center;
            }

            .subscriptions-table {
                display: block;
                overflow-x: auto;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }

            .bulk-controls {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                LeaderDesk<span>ADMIN</span>
            </div>
            
            <ul class="admin-nav">
                <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
                <li><a href="admin_teams.php">👥 Teams</a></li>
                <li><a href="admin_users.php">👤 Users</a></li>
                <li><a href="admin_subscriptions.php" class="active">💳 Subscriptions</a></li>
                <li><a href="admin_announcements.php">📢 Announcements</a></li>
                <li><a href="admin_settings.php">⚙️ Settings</a></li>
            </ul>
            
            <div class="back-to-app">
                <a href="logout.php">
                    <span>🚪</span>
                    Logout
                </a>
            </div>
        </aside>

        <main class="admin-main">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Subscription Management</h1>
                    <p>Manage team subscriptions and trials</p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-success" onclick="openModal('bulkExtendModal')">
                        <span>⏰</span> Bulk Extend Trials
                    </button>
                </div>
            </div>

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

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Teams</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['trial']; ?></div>
                    <div class="stat-label">On Trial</div>
                    <div class="stat-sub"><?php echo $stats['expired']; ?> expired</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active</div>
                    <div class="stat-sub">Paid subscriptions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['suspended']; ?></div>
                    <div class="stat-label">Suspended</div>
                </div>
            </div>

            <div class="revenue-card">
                <div class="revenue-info">
                    <h2>Estimated Monthly Revenue</h2>
                    <div class="revenue-amount">$<?php echo number_format($estimated_revenue); ?></div>
                    <div class="revenue-period">Based on <?php echo $stats['active']; ?> active subscriptions at $29/month</div>
                </div>
                <div class="revenue-badge">
                    💰 MRR
                </div>
            </div>

            <div class="bulk-actions">
                <h3>Bulk Actions</h3>
                <div class="bulk-controls">
                    <div class="checkbox-group">
                        <input type="checkbox" id="selectAll" onclick="toggleAll()">
                        <label for="selectAll">Select All</label>
                    </div>
                    <select id="bulkStatus" class="bulk-select">
                        <option value="">Change Status to...</option>
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button class="btn btn-outline" onclick="bulkUpdate()">Apply to Selected</button>
                </div>
            </div>

            <table class="subscriptions-table" id="subscriptionsTable">
                <thead>
                    <tr>
                        <th style="width: 40px">
                            <input type="checkbox" id="selectAllHeader" onclick="toggleAllFromHeader()">
                        </th>
                        <th>Team</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th>Trial Period</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): 
                        $trial_end = new DateTime($team['trial_end_date']);
                        $now = new DateTime();
                        $days_left = $now->diff($trial_end)->days;
                        $total_days = 60;
                        $progress_percent = min(100, (($total_days - $days_left) / $total_days) * 100);
                        
                        $progress_class = '';
                        if ($team['subscription_status'] == 'trial') {
                            if ($days_left < 0) {
                                $progress_class = 'danger';
                            } elseif ($days_left < 7) {
                                $progress_class = 'warning';
                            }
                        }
                    ?>
                        <tr data-id="<?php echo $team['id']; ?>">
                            <td>
                                <input type="checkbox" class="team-checkbox" value="<?php echo $team['id']; ?>">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                                <div style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($team['email']); ?></div>
                            </td>
                            <td><?php echo $team['user_count'] ?? 0; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $team['subscription_status']; ?>">
                                    <?php echo ucfirst($team['subscription_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($team['subscription_status'] == 'trial'): ?>
                                    <?php echo date('M d, Y', strtotime($team['trial_end_date'])); ?>
                                    <div class="trial-indicator">
                                        <div class="trial-progress <?php echo $progress_class; ?>" 
                                             style="width: <?php echo $progress_percent; ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    ---
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($team['subscription_status'] == 'trial'): ?>
                                    <?php if ($days_left < 0): ?>
                                        <span style="color: #dc2626; font-weight: 600;">Expired</span>
                                    <?php else: ?>
                                        <span style="color: <?php echo $days_left < 7 ? '#f59e0b' : '#1a1a1a'; ?>; font-weight: 600;">
                                            <?php echo $days_left; ?> days
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($team['subscription_status'] == 'expired'): ?>
                                    <span style="color: #dc2626;">Expired</span>
                                <?php elseif ($team['subscription_status'] == 'active'): ?>
                                    <span style="color: #10b981;">Paid</span>
                                <?php else: ?>
                                    ---
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <select class="status-select" onchange="quickUpdate(<?php echo $team['id']; ?>, this.value)">
                                        <option value="">Change</option>
                                        <option value="active">Active</option>
                                        <option value="trial">Trial</option>
                                        <option value="expired">Expired</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                    <?php if ($team['subscription_status'] == 'trial'): ?>
                                        <button class="btn-icon extend" onclick="extendTrial(<?php echo $team['id']; ?>)">Extend</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>

    <div id="extendTrialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Extend Trial Period</h2>
                <button class="modal-close" onclick="closeModal('extendTrialModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_subscription">
                <input type="hidden" name="team_id" id="extend_team_id">
                <input type="hidden" name="status" value="trial">
                
                <div class="form-group">
                    <label class="form-label">New Trial End Date</label>
                    <input type="date" name="trial_end" id="extend_date" class="form-input" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('extendTrialModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Extend Trial</button>
                </div>
            </form>
        </div>
    </div>

    <div id="bulkExtendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Bulk Extend Trials</h2>
                <button class="modal-close" onclick="closeModal('bulkExtendModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="bulkExtendForm">
                <input type="hidden" name="action" value="bulk_extend">
                
                <div class="form-group">
                    <label class="form-label">Extend by (days)</label>
                    <select name="days" class="form-select">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Apply to</label>
                    <select name="team_ids[]" multiple class="form-select" size="5" id="bulkTeamSelect">
                        <?php foreach ($teams as $team): ?>
                            <?php if ($team['subscription_status'] == 'trial'): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666;">Hold Ctrl/Cmd to select multiple</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('bulkExtendModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Extend Selected</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function extendTrial(teamId) {
            document.getElementById('extend_team_id').value = teamId;
            const date = new Date();
            date.setDate(date.getDate() + 30);
            document.getElementById('extend_date').value = date.toISOString().split('T')[0];
            openModal('extendTrialModal');
        }

        function quickUpdate(teamId, status) {
            if (!status) return;
            if (confirm('Update subscription status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="team_id" value="${teamId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.team-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        function toggleAllFromHeader() {
            const selectAll = document.getElementById('selectAllHeader');
            const checkboxes = document.querySelectorAll('.team-checkbox');
            const mainSelectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            if (mainSelectAll) mainSelectAll.checked = selectAll.checked;
        }

        function bulkUpdate() {
            const selected = [];
            document.querySelectorAll('.team-checkbox:checked').forEach(cb => {
                selected.push(cb.value);
            });
            
            if (selected.length === 0) {
                alert('Please select at least one team');
                return;
            }
            
            const status = document.getElementById('bulkStatus').value;
            if (!status) {
                alert('Please select a status');
                return;
            }
            
            if (confirm(`Change ${selected.length} team(s) to ${status}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="status" value="${status}">
                    ${selected.map(id => `<input type="hidden" name="team_ids[]" value="${id}">`).join('')}
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

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