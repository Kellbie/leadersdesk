<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Manage Teams";
$message = '';
$error = '';

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_team') {
        $team_name = $_POST['team_name'] ?? '';
        $country = $_POST['country'] ?? '';
        $state = $_POST['state'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if (empty($team_name) || empty($email)) {
            $error = 'Team name and email are required';
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
                $stmt->execute([$team_name]);
                if ($stmt->fetch()) {
                    throw new Exception('Team name already exists');
                }
                
                $stmt = $pdo->prepare("INSERT INTO teams (team_name, country, state_province, email, phone, trial_start_date, trial_end_date) VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY))");
                $stmt->execute([$team_name, $country, $state, $email, $phone]);
                $team_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO team_branding (team_id, tagline, primary_color, welcome_message) VALUES (?, 'Welcome to our team!', '#1a1a1a', 'Welcome to the team!')");
                $stmt->execute([$team_id]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "Team created successfully!";
                header("Location: admin_teams.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to create team: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'update_team') {
        $team_id = $_POST['team_id'] ?? 0;
        $team_name = $_POST['team_name'] ?? '';
        $country = $_POST['country'] ?? '';
        $state = $_POST['state'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'trial';
        
        if ($team_id) {
            $stmt = $pdo->prepare("UPDATE teams SET team_name = ?, country = ?, state_province = ?, email = ?, phone = ?, subscription_status = ? WHERE id = ?");
            $stmt->execute([$team_name, $country, $state, $email, $phone, $status, $team_id]);
            
            $_SESSION['success_message'] = "Team updated successfully!";
            header("Location: admin_teams.php");
            exit();
        }
    } elseif ($_POST['action'] == 'delete_team') {
        $team_id = $_POST['team_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            $error = "Cannot delete team with existing users.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$team_id]);
            
            $_SESSION['success_message'] = "Team deleted successfully!";
            header("Location: admin_teams.php");
            exit();
        }
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all teams
$stmt = $pdo->query("SELECT t.*, 
                     COUNT(u.id) as total_users,
                     SUM(CASE WHEN u.role = 'team_leader' THEN 1 ELSE 0 END) as leaders
                     FROM teams t
                     LEFT JOIN users u ON t.id = u.team_id
                     GROUP BY t.id
                     ORDER BY t.created_at DESC");
$teams = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teams - LeaderDesk Admin</title>
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

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
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
            letter-spacing: 0.5px;
        }

        .search-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 20px;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 2;
            min-width: 200px;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .filter-select {
            flex: 1;
            min-width: 120px;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 13px;
            background: white;
        }

        .teams-table-container {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .table-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
        }

        .table-header h2 {
            font-size: 16px;
            font-weight: 600;
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .teams-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f5f5f5;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            border-bottom: 1px solid #eaeaea;
        }

        .teams-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eaeaea;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Column widths */
        .teams-table th:nth-child(1) { width: 5%; }  /* ID */
        .teams-table th:nth-child(2) { width: 20%; } /* Team Name */
        .teams-table th:nth-child(3) { width: 15%; } /* Email */
        .teams-table th:nth-child(4) { width: 8%; }  /* Members */
        .teams-table th:nth-child(5) { width: 10%; } /* Created */
        .teams-table th:nth-child(6) { width: 10%; } /* Trial */
        .teams-table th:nth-child(7) { width: 8%; }  /* Status */
        .teams-table th:nth-child(8) { width: 12%; } /* Actions */

        .teams-table tr:hover {
            background: #fafafa;
        }

        .team-name {
            font-weight: 600;
            color: #1a1a1a;
        }

        .team-email {
            font-size: 12px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
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

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 10px;
            border: 1px solid #eaeaea;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
            text-decoration: none;
            color: #1a1a1a;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-icon:hover {
            background: #f5f5f5;
        }

        .btn-icon.view {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #0369a1;
        }

        .btn-icon.edit {
            background: #fef3c7;
            border-color: #fde68a;
            color: #92400e;
        }

        .btn-icon.delete {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
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
            border-radius: 20px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #888;
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
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #eaeaea;
            border-radius: 10px;
            font-size: 14px;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
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

        .search-results {
            margin-top: 12px;
            font-size: 13px;
            color: #666;
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
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                flex-direction: column;
            }

            .search-input,
            .filter-select {
                width: 100%;
            }

            .teams-table {
                min-width: 800px;
            }

            .teams-table-container {
                overflow-x: auto;
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
                <li><a href="admin_teams.php" class="active">👥 Teams</a></li>
                <li><a href="admin_users.php">👤 Users</a></li>
                <li><a href="admin_subscriptions.php">💳 Subscriptions</a></li>
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

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Manage Teams</h1>
                    <p><?php echo count($teams); ?> total teams</p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('createTeamModal')">
                        <span>+</span> New Team
                    </button>
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
                <?php
                $total_teams = count($teams);
                $active_teams = count(array_filter($teams, fn($t) => $t['subscription_status'] == 'active'));
                $trial_teams = count(array_filter($teams, fn($t) => $t['subscription_status'] == 'trial'));
                ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_teams; ?></div>
                    <div class="stat-label">Total Teams</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $active_teams; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $trial_teams; ?></div>
                    <div class="stat-label">On Trial</div>
                </div>
            </div>

            <!-- Search -->
            <div class="search-section">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by team name or email..." onkeyup="filterTeams()">
                    <select id="statusFilter" class="filter-select" onchange="filterTeams()">
                        <option value="all">All Status</option>
                        <option value="trial">Trial</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>

            <!-- Teams Table -->
            <div class="teams-table-container">
                <div class="table-header">
                    <h2>All Teams</h2>
                </div>
                
                <?php if (empty($teams)): ?>
                    <div class="empty-state">
                        <span>👥</span>
                        <h3>No teams yet</h3>
                        <p>Create your first team</p>
                    </div>
                <?php else: ?>
                    <table class="teams-table" id="teamsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Team</th>
                                <th>Email</th>
                                <th>Members</th>
                                <th>Created</th>
                                <th>Trial</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="teamsTableBody">
                            <?php foreach ($teams as $team): 
                                $trial_end = new DateTime($team['trial_end_date']);
                                $now = new DateTime();
                                $days_left = $now->diff($trial_end)->days;
                            ?>
                                <tr data-status="<?php echo $team['subscription_status']; ?>" 
                                    data-search="<?php echo strtolower($team['team_name'] . ' ' . $team['email']); ?>">
                                    
                                    <td>#<?php echo $team['id']; ?></td>
                                    
                                    <td>
                                        <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                    </td>
                                    
                                    <td>
                                        <div class="team-email"><?php echo htmlspecialchars($team['email']); ?></div>
                                    </td>
                                    
                                    <td><?php echo $team['total_users'] ?? 0; ?></td>
                                    
                                    <td><?php echo date('m/d/y', strtotime($team['created_at'])); ?></td>
                                    
                                    <td>
                                        <?php if ($team['subscription_status'] == 'trial'): ?>
                                            <?php echo date('m/d', strtotime($team['trial_end_date'])); ?>
                                            <span style="font-size: 10px; color: <?php echo $days_left < 7 ? '#dc2626' : '#888'; ?>">
                                                (<?php echo $days_left; ?>d)
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge status-<?php echo $team['subscription_status']; ?>">
                                            <?php echo ucfirst($team['subscription_status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <div class="action-buttons">
                                            <a href="admin_team_detail.php?id=<?php echo $team['id']; ?>" class="btn-icon view">👁️</a>
                                            <button class="btn-icon edit" onclick="editTeam(<?php echo $team['id']; ?>)">✏️</button>
                                            <?php if ($team['total_users'] == 0): ?>
                                                <button class="btn-icon delete" onclick="deleteTeam(<?php echo $team['id']; ?>)">🗑️</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Team Modal -->
    <div id="createTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Team</h2>
                <button class="modal-close" onclick="closeModal('createTeamModal')">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_team">
                
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="team_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createTeamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Team</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div id="editTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Team</h2>
                <button class="modal-close" onclick="closeModal('editTeamModal')">&times;</button>
            </div>
            
            <form method="POST" id="editTeamForm">
                <input type="hidden" name="action" value="update_team">
                <input type="hidden" name="team_id" id="edit_team_id">
                
                <div class="form-group">
                    <label class="form-label">Team Name</label>
                    <input type="text" name="team_name" id="edit_team_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" id="edit_country" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" id="edit_state" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" id="edit_phone" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="trial">Trial</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editTeamModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Team</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Team</h2>
                <button class="modal-close" onclick="closeModal('deleteTeamModal')">&times;</button>
            </div>
            
            <p style="margin: 16px 0;">Are you sure you want to delete this team?</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete_team">
                <input type="hidden" name="team_id" id="delete_team_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteTeamModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
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

        function editTeam(teamId) {
            fetch('ajax/get_team.php?id=' + teamId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_team_id').value = data.team.id;
                        document.getElementById('edit_team_name').value = data.team.team_name;
                        document.getElementById('edit_email').value = data.team.email;
                        document.getElementById('edit_country').value = data.team.country || '';
                        document.getElementById('edit_state').value = data.team.state_province || '';
                        document.getElementById('edit_phone').value = data.team.phone || '';
                        document.getElementById('edit_status').value = data.team.subscription_status;
                        openModal('editTeamModal');
                    }
                });
        }

        function deleteTeam(teamId) {
            document.getElementById('delete_team_id').value = teamId;
            openModal('deleteTeamModal');
        }

        function filterTeams() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#teamsTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const status = row.dataset.status;
                const searchText = row.dataset.search;
                
                const statusMatch = statusFilter === 'all' || status === statusFilter;
                const searchMatch = searchInput === '' || searchText.includes(searchInput);
                
                if (statusMatch && searchMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('searchResults').textContent = `Showing ${visibleCount} of ${rows.length} teams`;
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        filterTeams();
    </script>
</body>
</html>