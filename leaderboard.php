<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Leaderboard";

// Get filter from query string
$time_filter = $_GET['filter'] ?? 'monthly';
$valid_filters = ['weekly', 'monthly', 'all_time'];
if (!in_array($time_filter, $valid_filters)) {
    $time_filter = 'monthly';
}

// Get leaderboard data based on time filter
switch ($time_filter) {
    case 'weekly':
        $date_condition = "AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    case 'monthly':
        $date_condition = "AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    default:
        $date_condition = "";
}

// Main leaderboard query with points from activity_logs
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        mp.rank,
        mp.activity_score as total_score,
        mp.total_recruits,
        mp.total_sales,
        COALESCE(SUM(al.points_earned), 0) as period_score,
        COUNT(DISTINCT al.id) as activities_count,
        (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = u.id) as badge_count
    FROM users u
    JOIN member_profiles mp ON u.id = mp.user_id
    LEFT JOIN activity_logs al ON u.id = al.user_id AND al.team_id = u.team_id $date_condition
    WHERE u.team_id = ? AND u.role = 'member'
    GROUP BY u.id
    ORDER BY period_score DESC, mp.activity_score DESC
");
$stmt->execute([$current_user['team_id']]);
$leaderboard = $stmt->fetchAll();

// Get current user's rank
$current_user_rank = 0;
foreach ($leaderboard as $index => $member) {
    if ($member['id'] == $current_user['id']) {
        $current_user_rank = $index + 1;
        break;
    }
}

// Get top 3 for podium
$top_three = array_slice($leaderboard, 0, 3);

// Get all badges
$stmt = $pdo->prepare("SELECT * FROM badges");
$stmt->execute();
$badges = $stmt->fetchAll();

// Get user badges
$stmt = $pdo->prepare("
    SELECT ub.user_id, b.name, b.icon, b.description
    FROM user_badges ub 
    JOIN badges b ON ub.badge_id = b.id 
    WHERE ub.team_id = ?
");
$stmt->execute([$current_user['team_id']]);
$user_badges = [];
while ($row = $stmt->fetch()) {
    if (!isset($user_badges[$row['user_id']])) {
        $user_badges[$row['user_id']] = [];
    }
    $user_badges[$row['user_id']][] = $row;
}

// Calculate statistics
$total_participants = count($leaderboard);
$avg_score = $total_participants > 0 ? round(array_sum(array_column($leaderboard, 'period_score')) / $total_participants) : 0;
$top_score = !empty($leaderboard) ? $leaderboard[0]['period_score'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - LeaderDesk</title>
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

        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 100px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            color: #1a1a1a;
        }

        .filter-tab:hover {
            background: #f5f5f5;
        }

        .filter-tab.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
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

        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .stat-sub {
            font-size: 13px;
            color: #888;
        }

        .current-user-rank {
            background: white;
            border-radius: 16px;
            border: 2px solid #1a1a1a;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            animation: pulse 2s infinite;
        }

        .rank-badge-large {
            width: 60px;
            height: 60px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
        }

        .user-rank-info h3 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        .user-rank-info p {
            color: #666;
            font-size: 14px;
        }

        .podium-container {
            margin-bottom: 40px;
        }

        .podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .podium-item {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .podium-item.first {
            order: 2;
        }

        .podium-item.second {
            order: 1;
        }

        .podium-item.third {
            order: 3;
        }

        .podium-avatar {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 50%;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            border: 3px solid;
        }

        .first .podium-avatar {
            width: 100px;
            height: 100px;
            font-size: 40px;
            border-color: #fbbf24;
            background: #fef3c7;
        }

        .second .podium-avatar {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        .third .podium-avatar {
            border-color: #b45309;
            background: #fed7aa;
        }

        .podium-rank {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .first .podium-rank {
            color: #fbbf24;
        }

        .second .podium-rank {
            color: #94a3b8;
        }

        .third .podium-rank {
            color: #b45309;
        }

        .podium-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .podium-score {
            font-size: 14px;
            color: #666;
        }

        .crown {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 32px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-5px); }
        }

        .leaderboard-container {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
            margin-bottom: 32px;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }

        .leaderboard-table th {
            background: #fafafa;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            border-bottom: 1px solid #eaeaea;
        }

        .leaderboard-table td {
            padding: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .leaderboard-table tr:last-child td {
            border-bottom: none;
        }

        .leaderboard-table tr:hover {
            background: #fafafa;
        }

        .leaderboard-table tr.current-user {
            background: #f5f5f5;
            border-left: 4px solid #1a1a1a;
        }

        .rank-cell {
            font-weight: 700;
            width: 60px;
        }

        .rank-1 {
            color: #fbbf24;
        }

        .rank-2 {
            color: #94a3b8;
        }

        .rank-3 {
            color: #b45309;
        }

        .member-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .member-avatar-small {
            width: 36px;
            height: 36px;
            background: #1a1a1a;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .member-info {
            display: flex;
            flex-direction: column;
        }

        .member-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .member-rank {
            font-size: 12px;
            color: #888;
        }

        .score-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .score-value {
            font-weight: 700;
            min-width: 50px;
        }

        .score-bar-container {
            flex: 1;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .score-bar {
            height: 100%;
            background: #1a1a1a;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .badges-container {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .badge-icon {
            font-size: 18px;
            cursor: help;
            position: relative;
        }

        .badge-icon:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a1a;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 10;
        }

        .badges-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
        }

        .badges-section h2 {
            font-size: 18px;
            margin-bottom: 16px;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .badge-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
            transition: all 0.2s;
        }

        .badge-card:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .badge-icon-large {
            font-size: 32px;
        }

        .badge-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .badge-info p {
            font-size: 12px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #888;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(26, 26, 26, 0.2);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(26, 26, 26, 0);
            }
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .leaderboard-table {
                min-width: 800px;
            }

            .leaderboard-container {
                overflow-x: auto;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .podium {
                flex-direction: column;
                align-items: center;
            }

            .podium-item {
                width: 100%;
                max-width: 300px;
            }

            .podium-item.first {
                order: 1;
            }

            .podium-item.second {
                order: 2;
            }

            .podium-item.third {
                order: 3;
            }

            .badges-grid {
                grid-template-columns: 1fr;
            }

            .current-user-rank {
                flex-direction: column;
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
                <li class="nav-item active">
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
                    <h1>Leaderboard</h1>
                    <p>See who's leading the pack</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?filter=weekly" class="filter-tab <?php echo $time_filter == 'weekly' ? 'active' : ''; ?>">This Week</a>
                <a href="?filter=monthly" class="filter-tab <?php echo $time_filter == 'monthly' ? 'active' : ''; ?>">This Month</a>
                <a href="?filter=all_time" class="filter-tab <?php echo $time_filter == 'all_time' ? 'active' : ''; ?>">All Time</a>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Participants</div>
                    <div class="stat-value"><?php echo $total_participants; ?></div>
                    <div class="stat-sub">Active members</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Average Score</div>
                    <div class="stat-value"><?php echo $avg_score; ?></div>
                    <div class="stat-sub">Per member</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Top Score</div>
                    <div class="stat-value"><?php echo $top_score; ?></div>
                    <div class="stat-sub">Current leader</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Your Rank</div>
                    <div class="stat-value">#<?php echo $current_user_rank ?: '-'; ?></div>
                    <div class="stat-sub">Out of <?php echo $total_participants; ?></div>
                </div>
            </div>

            <!-- Current User Rank (if not in top 3) -->
            <?php if ($current_user_rank > 3 && $current_user_rank <= $total_participants): ?>
                <div class="current-user-rank">
                    <div class="rank-badge-large">#<?php echo $current_user_rank; ?></div>
                    <div class="user-rank-info">
                        <h3><?php echo htmlspecialchars($current_user['name']); ?></h3>
                        <p>Your current rank • <?php echo $leaderboard[$current_user_rank-1]['period_score']; ?> points this <?php echo $time_filter == 'weekly' ? 'week' : ($time_filter == 'monthly' ? 'month' : 'period'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Podium (Top 3) -->
            <?php if (!empty($top_three) && count($top_three) >= 3): ?>
                <div class="podium-container">
                    <div class="podium">
                        <!-- 2nd Place -->
                        <div class="podium-item second">
                            <div class="crown">🥈</div>
                            <div class="podium-avatar">
                                <?php echo strtoupper(substr($top_three[1]['name'], 0, 1)); ?>
                            </div>
                            <div class="podium-rank">#2</div>
                            <div class="podium-name"><?php echo htmlspecialchars($top_three[1]['name']); ?></div>
                            <div class="podium-score"><?php echo $top_three[1]['period_score']; ?> pts</div>
                        </div>
                        
                        <!-- 1st Place -->
                        <div class="podium-item first">
                            <div class="crown">👑</div>
                            <div class="podium-avatar">
                                <?php echo strtoupper(substr($top_three[0]['name'], 0, 1)); ?>
                            </div>
                            <div class="podium-rank">#1</div>
                            <div class="podium-name"><?php echo htmlspecialchars($top_three[0]['name']); ?></div>
                            <div class="podium-score"><?php echo $top_three[0]['period_score']; ?> pts</div>
                        </div>
                        
                        <!-- 3rd Place -->
                        <div class="podium-item third">
                            <div class="crown">🥉</div>
                            <div class="podium-avatar">
                                <?php echo strtoupper(substr($top_three[2]['name'], 0, 1)); ?>
                            </div>
                            <div class="podium-rank">#3</div>
                            <div class="podium-name"><?php echo htmlspecialchars($top_three[2]['name']); ?></div>
                            <div class="podium-score"><?php echo $top_three[2]['period_score']; ?> pts</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Full Leaderboard -->
            <?php if (empty($leaderboard)): ?>
                <div class="empty-state">
                    <span>🏆</span>
                    <h3>No leaderboard data yet</h3>
                    <p>Start adding activities to see rankings</p>
                </div>
            <?php else: ?>
                <div class="leaderboard-container">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Member</th>
                                <th>Period Score</th>
                                <th>Total Score</th>
                                <th>Recruits</th>
                                <th>Sales</th>
                                <th>Badges</th>
                                <th>Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $member): 
                                $rank = $index + 1;
                                $is_current_user = $member['id'] == $current_user['id'];
                            ?>
                                <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                                    <td class="rank-cell">
                                        <span class="rank-<?php echo $rank; ?>">#<?php echo $rank; ?></span>
                                    </td>
                                    
                                    <td>
                                        <div class="member-cell">
                                            <div class="member-avatar-small">
                                                <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                            </div>
                                            <div class="member-info">
                                                <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                                <div class="member-rank"><?php echo htmlspecialchars($member['rank'] ?? 'Member'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="score-cell">
                                            <span class="score-value"><?php echo $member['period_score']; ?></span>
                                            <div class="score-bar-container">
                                                <?php $percentage = $top_score > 0 ? ($member['period_score'] / $top_score) * 100 : 0; ?>
                                                <div class="score-bar" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td><?php echo $member['total_score']; ?></td>
                                    <td><?php echo $member['total_recruits']; ?></td>
                                    <td>₦<?php echo number_format($member['total_sales']); ?></td>
                                    
                                    <td>
                                        <div class="badges-container">
                                            <?php if (isset($user_badges[$member['id']])): ?>
                                                <?php foreach ($user_badges[$member['id']] as $badge): ?>
                                                    <span class="badge-icon" data-tooltip="<?php echo htmlspecialchars($badge['name'] . ': ' . $badge['description']); ?>">
                                                        <?php echo $badge['icon']; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span style="color: #888;">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td><?php echo $member['activities_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Badges Showcase -->
            <div class="badges-section">
                <h2>Available Badges</h2>
                <div class="badges-grid">
                    <?php foreach ($badges as $badge): ?>
                        <div class="badge-card">
                            <div class="badge-icon-large"><?php echo $badge['icon']; ?></div>
                            <div class="badge-info">
                                <h4><?php echo htmlspecialchars($badge['name']); ?></h4>
                                <p><?php echo htmlspecialchars($badge['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Update notification badges
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
                            sideBadge.style.display = 'none';
                        }
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
        });

        // Add animation to bars on load
        window.addEventListener('load', function() {
            document.querySelectorAll('.score-bar').forEach(bar => {
                bar.style.transition = 'width 1s ease-out';
            });
        });
    </script>
</body>
</html>