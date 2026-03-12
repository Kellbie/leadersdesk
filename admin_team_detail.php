<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$team_id) {
    header("Location: admin_teams.php");
    exit();
}

// Get team details
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header("Location: admin_teams.php");
    exit();
}

// Get team members
$stmt = $pdo->prepare("SELECT u.*, mp.rank, mp.join_date FROM users u LEFT JOIN member_profiles mp ON u.id = mp.user_id WHERE u.team_id = ? ORDER BY u.role, u.created_at DESC");
$stmt->execute([$team_id]);
$members = $stmt->fetchAll();

$page_title = "Team Details - " . $team['team_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($team['team_name']); ?> - LeaderDesk Admin</title>
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

        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: #1a1a1a;
        }

        .team-header {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .team-info h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .team-meta {
            display: flex;
            gap: 24px;
            color: #666;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .members-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eaeaea;
            background: #fafafa;
            font-weight: 600;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
        }

        .members-table th {
            text-align: left;
            padding: 12px 16px;
            background: #f5f5f5;
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }

        .members-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #eaeaea;
            font-size: 13px;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .role-leader {
            background: #fef3c7;
            color: #92400e;
        }

        .role-member {
            background: #e0f2fe;
            color: #0369a1;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-main {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <h1>Team Details</h1>
                    <p><?php echo htmlspecialchars($team['team_name']); ?></p>
                </div>
                
                <a href="admin_teams.php" class="back-link">
                    <span>←</span> Back to Teams
                </a>
            </div>

            <!-- Team Header -->
            <div class="team-header">
                <div class="team-info">
                    <h2><?php echo htmlspecialchars($team['team_name']); ?></h2>
                    <div class="team-meta">
                        <span>📧 <?php echo htmlspecialchars($team['email']); ?></span>
                        <?php if ($team['phone']): ?>
                            <span>📞 <?php echo htmlspecialchars($team['phone']); ?></span>
                        <?php endif; ?>
                        <?php if ($team['country']): ?>
                            <span>📍 <?php echo htmlspecialchars($team['country']); ?><?php echo $team['state_province'] ? ', ' . htmlspecialchars($team['state_province']) : ''; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge status-<?php echo $team['subscription_status']; ?>">
                        <?php echo ucfirst($team['subscription_status']); ?>
                    </span>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <?php
                $total_members = count($members);
                $leaders = count(array_filter($members, fn($m) => $m['role'] == 'team_leader'));
                $active = count(array_filter($members, fn($m) => $m['status'] == 'active'));
                ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $leaders; ?></div>
                    <div class="stat-label">Leaders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $active; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('M d, Y', strtotime($team['created_at'])); ?></div>
                    <div class="stat-label">Created</div>
                </div>
            </div>

            <!-- Members List -->
            <div class="members-section">
                <div class="section-header">Team Members (<?php echo $total_members; ?>)</div>
                
                <?php if (empty($members)): ?>
                    <div style="text-align: center; padding: 40px; color: #888;">
                        No members in this team yet.
                    </div>
                <?php else: ?>
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Rank</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $member['role'] == 'team_leader' ? 'leader' : 'member'; ?>">
                                            <?php echo $member['role'] == 'team_leader' ? 'Leader' : 'Member'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['rank'] ?? '-'); ?></td>
                                    <td>
                                        <span style="color: <?php echo $member['status'] == 'active' ? '#10b981' : '#ef4444'; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($member['join_date'] ?? $member['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>