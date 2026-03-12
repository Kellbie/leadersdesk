<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$page_title = "Manage Users";
$message = '';
$error = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_user') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $team_id = $_POST['team_id'] ?? null;
        $password = bin2hex(random_bytes(4)); // Generate random password
        
        if (empty($name) || empty($email) || empty($team_id)) {
            $error = 'Name, email, and team are required';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists');
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, team_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $email, $phone, $hashed_password, $role, $team_id]);
                $user_id = $pdo->lastInsertId();
                
                // Create member profile
                $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Member', 'member', CURDATE())");
                $stmt->execute([$user_id, $team_id]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "User created successfully! Password: $password";
                header("Location: admin_users.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to create user: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'update_user') {
        $user_id = $_POST['user_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $team_id = $_POST['team_id'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        if ($user_id) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, team_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $role, $team_id, $status, $user_id]);
            
            $_SESSION['success_message'] = "User updated successfully!";
            header("Location: admin_users.php");
            exit();
        }
    } elseif ($_POST['action'] == 'reset_password') {
        $user_id = $_POST['user_id'] ?? 0;
        $new_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        $_SESSION['success_message'] = "Password reset successfully! New password: $new_password";
        header("Location: admin_users.php");
        exit();
    } elseif ($_POST['action'] == 'delete_user') {
        $user_id = $_POST['user_id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success_message'] = "User deleted successfully!";
        header("Location: admin_users.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all teams for dropdown
$stmt = $pdo->query("SELECT id, team_name FROM teams ORDER BY team_name");
$teams = $stmt->fetchAll();

// Get all users with team info
$stmt = $pdo->query("SELECT u.*, t.team_name, mp.rank, mp.activity_score 
                     FROM users u 
                     LEFT JOIN teams t ON u.team_id = t.id 
                     LEFT JOIN member_profiles mp ON u.id = mp.user_id
                     ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - LeaderDesk Admin</title>
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

        /* Admin Sidebar */
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

        /* Main Content */
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        /* Alert Messages */
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

        /* Stats Cards */
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

        /* Filters */
        .filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background: white;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #666;
            text-transform: uppercase;
        }

        .filter-select,
        .filter-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
        }

        /* Users Table */
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .users-table th {
            background: #f5f5f5;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        .users-table td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .users-table tr:hover {
            background: #fafafa;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .user-email {
            font-size: 12px;
            color: #666;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .role-super_admin {
            background: #1a1a1a;
            color: white;
        }

        .role-team_leader {
            background: #fef3c7;
            color: #92400e;
        }

        .role-member {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: #ecfdf5;
            color: #065f46;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #475569;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
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

        .btn-icon.reset:hover {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .btn-icon.delete:hover {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        /* Modal */
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
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
        }

        .password-note {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        /* Animations */
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

        /* Responsive */
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

            .users-table {
                display: block;
                overflow-x: auto;
            }

            .filters {
                flex-direction: column;
            }

            .form-row {
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
                <li><a href="admin_users.php" class="active">👤 Users</a></li>
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
                    <h1>Manage Users</h1>
                    <p>View and manage all users on the platform</p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('createUserModal')">
                        <span>+</span> Add User
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
                $total_users = count($users);
                $total_admins = count(array_filter($users, function($u) { return $u['role'] == 'super_admin'; }));
                $total_leaders = count(array_filter($users, function($u) { return $u['role'] == 'team_leader'; }));
                $total_members = count(array_filter($users, function($u) { return $u['role'] == 'member'; }));
                ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_admins; ?></div>
                    <div class="stat-label">Super Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_leaders; ?></div>
                    <div class="stat-label">Team Leaders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-label">Members</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <div class="filter-label">Search</div>
                    <input type="text" id="searchInput" class="filter-input" placeholder="Name or email..." onkeyup="filterUsers()">
                </div>
                <div class="filter-group">
                    <div class="filter-label">Role</div>
                    <select id="roleFilter" class="filter-select" onchange="filterUsers()">
                        <option value="all">All Roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="team_leader">Team Leader</option>
                        <option value="member">Member</option>
                    </select>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Status</div>
                    <select id="statusFilter" class="filter-select" onchange="filterUsers()">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <div class="filter-label">Team</div>
                    <select id="teamFilter" class="filter-select" onchange="filterUsers()">
                        <option value="all">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Users Table -->
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Team</th>
                        <th>Status</th>
                        <th>Rank</th>
                        <th>Score</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['status']; ?>" data-team="<?php echo $user['team_id']; ?>" data-name="<?php echo strtolower($user['name']); ?>" data-email="<?php echo strtolower($user['email']); ?>">
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['team_name'] ?? 'No Team'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['rank'] ?? '-'); ?></td>
                            <td><?php echo $user['activity_score'] ?? 0; ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                                    <button class="btn-icon reset" onclick="resetPassword(<?php echo $user['id']; ?>)">Reset PW</button>
                                    <?php if ($user['role'] != 'super_admin'): ?>
                                        <button class="btn-icon delete" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="member">Member</option>
                            <option value="team_leader">Team Leader</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team *</label>
                        <select name="team_id" class="form-select" required>
                            <option value="">Select Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="password-note">
                    A random password will be generated and shown after creation.
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" id="edit_phone" class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="member">Member</option>
                            <option value="team_leader">Team Leader</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team</label>
                    <select name="team_id" id="edit_team" class="form-select">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reset Password</h2>
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to reset this user's password? A new random password will be generated.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete User</h2>
                <button class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this user? This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
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

        function editUser(userId) {
            fetch('ajax/get_user.php?id=' + userId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_user_id').value = data.id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_phone').value = data.phone || '';
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('edit_team').value = data.team_id || '';
                    
                    openModal('editUserModal');
                });
        }

        function resetPassword(userId) {
            document.getElementById('reset_user_id').value = userId;
            openModal('resetPasswordModal');
        }

        function deleteUser(userId) {
            document.getElementById('delete_user_id').value = userId;
            openModal('deleteUserModal');
        }

        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const teamFilter = document.getElementById('teamFilter').value;
            
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const role = row.dataset.role;
                const status = row.dataset.status;
                const team = row.dataset.team;
                const name = row.dataset.name;
                const email = row.dataset.email;
                
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRole = roleFilter === 'all' || role === roleFilter;
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                const matchesTeam = teamFilter === 'all' || team === teamFilter;
                
                if (matchesSearch && matchesRole && matchesStatus && matchesTeam) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

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