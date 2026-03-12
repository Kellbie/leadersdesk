<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Events";
$message = '';
$error = '';

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_event') {
        $title = $_POST['title'] ?? '';
        $event_type = $_POST['event_type'] ?? '';
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $location = $_POST['location'] ?? '';
        $meeting_link = $_POST['meeting_link'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($title) || empty($event_type) || empty($event_date) || empty($event_time)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO events (team_id, created_by, title, event_type, event_date, event_time, location, meeting_link, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $title, $event_type, $event_date, $event_time, $location, $meeting_link, $description]);
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'create_event', ?, 15)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], "Created event: $title"]);
                
                $_SESSION['success_message'] = "Event created successfully!";
                header("Location: events.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to create event';
            }
        }
    } elseif ($_POST['action'] == 'rsvp_event') {
        $event_id = $_POST['event_id'] ?? 0;
        $response = $_POST['response'] ?? '';
        
        if ($event_id && $response) {
            try {
                // Check if already responded
                $stmt = $pdo->prepare("SELECT id FROM event_attendance WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $current_user['id']]);
                
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE event_attendance SET response = ? WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$response, $event_id, $current_user['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO event_attendance (event_id, user_id, response) VALUES (?, ?, ?)");
                    $stmt->execute([$event_id, $current_user['id'], $response]);
                    
                    // Award points for attending
                    if ($response == 'attending') {
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'rsvp_event', ?, 10)");
                        $stmt->execute([$current_user['team_id'], $current_user['id'], "RSVP'd attending to event"]);
                    }
                }
                
                $_SESSION['success_message'] = "RSVP updated successfully!";
                header("Location: events.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to update RSVP';
            }
        }
    } elseif ($_POST['action'] == 'delete_event') {
        $event_id = $_POST['event_id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND team_id = ?");
        $stmt->execute([$event_id, $current_user['team_id']]);
        
        $_SESSION['success_message'] = "Event deleted successfully!";
        header("Location: events.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all events
$stmt = $pdo->prepare("SELECT e.*, 
                       COUNT(ea.id) as total_attendees,
                       SUM(CASE WHEN ea.response = 'attending' THEN 1 ELSE 0 END) as attending_count,
                       u.name as creator_name 
                       FROM events e 
                       LEFT JOIN event_attendance ea ON e.id = ea.event_id 
                       JOIN users u ON e.created_by = u.id 
                       WHERE e.team_id = ? 
                       GROUP BY e.id 
                       ORDER BY e.event_date DESC");
$stmt->execute([$current_user['team_id']]);
$events = $stmt->fetchAll();

// Get user's RSVPs
$stmt = $pdo->prepare("SELECT event_id, response FROM event_attendance WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$user_responses = [];
while ($row = $stmt->fetch()) {
    $user_responses[$row['event_id']] = $row['response'];
}

// Separate upcoming and past events
$today = date('Y-m-d');
$upcoming_events = array_filter($events, function($e) use ($today) {
    return $e['event_date'] >= $today;
});
$past_events = array_filter($events, function($e) use ($today) {
    return $e['event_date'] < $today;
});

// Statistics
$total_upcoming = count($upcoming_events);
$total_attending = array_sum(array_column($upcoming_events, 'attending_count'));
$events_this_month = count(array_filter($upcoming_events, function($e) {
    return date('Y-m', strtotime($e['event_date'])) == date('Y-m');
}));
$my_rsvps = count(array_filter($upcoming_events, function($e) use ($user_responses) {
    return isset($user_responses[$e['id']]) && $user_responses[$e['id']] == 'attending';
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - LeaderDesk</title>
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

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
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

        .view-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }

        .view-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .view-btn:hover {
            background: #f5f5f5;
        }

        .view-btn.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
            transition: all 0.2s;
            animation: fadeIn 0.3s ease-out;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);
        }

        .event-card.past {
            opacity: 0.7;
        }

        .event-date-banner {
            background: #1a1a1a;
            color: white;
            padding: 16px;
            text-align: center;
        }

        .event-date-banner .day {
            font-size: 36px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .event-date-banner .month {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }

        .event-content {
            padding: 20px;
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .event-title {
            font-size: 20px;
            font-weight: 600;
        }

        .event-type {
            padding: 4px 8px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-training {
            background: #e0f2fe;
            color: #0369a1;
        }

        .type-opportunity {
            background: #fef3c7;
            color: #92400e;
        }

        .type-team_meeting {
            background: #ecfdf5;
            color: #065f46;
        }

        .type-product_presentation {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .event-description {
            color: #666;
            font-size: 14px;
            margin: 12px 0;
            line-height: 1.6;
        }

        .event-details {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-icon {
            width: 20px;
            color: #666;
        }

        .meeting-link {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }

        .meeting-link:hover {
            text-decoration: underline;
        }

        .event-stats {
            display: flex;
            gap: 16px;
            margin: 16px 0;
            padding: 12px 0;
            border-top: 1px solid #eaeaea;
            border-bottom: 1px solid #eaeaea;
        }

        .stat {
            flex: 1;
            text-align: center;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .stat-label {
            font-size: 12px;
            color: #888;
        }

        .rsvp-buttons {
            display: flex;
            gap: 8px;
            margin: 16px 0;
        }

        .btn-rsvp {
            flex: 1;
            padding: 10px;
            border: 1px solid #eaeaea;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-rsvp:hover {
            background: #f5f5f5;
        }

        .btn-rsvp.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .btn-rsvp.attending.active {
            background: #10b981;
            border-color: #10b981;
        }

        .btn-rsvp.maybe.active {
            background: #f59e0b;
            border-color: #f59e0b;
        }

        .btn-rsvp.not-attending.active {
            background: #ef4444;
            border-color: #ef4444;
        }

        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eaeaea;
            font-size: 13px;
            color: #888;
        }

        .event-actions {
            display: flex;
            gap: 8px;
        }

        .btn-delete {
            padding: 6px 12px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            color: #991b1b;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .calendar-view {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            padding: 24px;
            min-height: 600px;
            display: none;
        }

        .calendar-view.show {
            display: block;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .calendar-month {
            font-size: 20px;
            font-weight: 600;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: 600;
            margin-bottom: 8px;
            color: #666;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .calendar-day {
            aspect-ratio: 1;
            padding: 8px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background: #f5f5f5;
        }

        .calendar-day.has-event {
            background: #f5f5f5;
            border-color: #1a1a1a;
            font-weight: 600;
        }

        .calendar-day.has-event:hover {
            background: #eaeaea;
        }

        .calendar-day.empty {
            background: #fafafa;
            border-color: #f0f0f0;
            cursor: default;
        }

        .calendar-day.empty:hover {
            background: #fafafa;
        }

        .event-indicator {
            width: 6px;
            height: 6px;
            background: #1a1a1a;
            border-radius: 50%;
            margin-top: 4px;
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
            min-height: 100px;
            resize: vertical;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
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
            margin-bottom: 24px;
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

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .events-grid {
                grid-template-columns: 1fr;
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .rsvp-buttons {
                flex-wrap: wrap;
            }

            .event-header {
                flex-direction: column;
                align-items: flex-start;
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
                <li class="nav-item active">
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
                    <h1>Events</h1>
                    <p>Schedule and manage team events</p>
                </div>
                
                <div class="header-actions">
                    <?php if ($current_user['role'] == 'team_leader'): ?>
                        <button class="btn btn-primary" onclick="openModal('createEventModal')">
                            <span>+</span> Create Event
                        </button>
                    <?php endif; ?>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Upcoming Events</div>
                    <div class="stat-value"><?php echo $total_upcoming; ?></div>
                    <div class="stat-sub">Scheduled</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Attending</div>
                    <div class="stat-value"><?php echo $total_attending; ?></div>
                    <div class="stat-sub">Confirmed RSVPs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value"><?php echo $events_this_month; ?></div>
                    <div class="stat-sub">Events in <?php echo date('F'); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">My RSVPs</div>
                    <div class="stat-value"><?php echo $my_rsvps; ?></div>
                    <div class="stat-sub">Events attending</div>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="showView('grid')">📋 Grid View</button>
                <button class="view-btn" onclick="showView('calendar')">📅 Calendar View</button>
            </div>

            <!-- Grid View -->
            <div id="gridView" class="events-grid">
                <?php if (empty($upcoming_events) && empty($past_events)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <span>📅</span>
                        <h3>No events scheduled</h3>
                        <p>Create your first event to get started</p>
                        <?php if ($current_user['role'] == 'team_leader'): ?>
                            <button class="btn btn-primary" onclick="openModal('createEventModal')">Create Event</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Upcoming Events -->
                    <?php if (!empty($upcoming_events)): ?>
                        <div style="grid-column: 1/-1; margin-bottom: 8px;">
                            <h2 style="font-size: 18px; font-weight: 600;">Upcoming Events</h2>
                        </div>
                        <?php foreach ($upcoming_events as $event): 
                            $event_date = new DateTime($event['event_date']);
                            $user_response = $user_responses[$event['id']] ?? null;
                            
                            $type_classes = [
                                'training' => 'type-training',
                                'opportunity' => 'type-opportunity',
                                'team_meeting' => 'type-team_meeting',
                                'product_presentation' => 'type-product_presentation'
                            ];
                            $type_labels = [
                                'training' => 'Training',
                                'opportunity' => 'Opportunity Meeting',
                                'team_meeting' => 'Team Meeting',
                                'product_presentation' => 'Product Presentation'
                            ];
                        ?>
                            <div class="event-card">
                                <div class="event-date-banner">
                                    <div class="day"><?php echo $event_date->format('d'); ?></div>
                                    <div class="month"><?php echo $event_date->format('M'); ?></div>
                                </div>
                                
                                <div class="event-content">
                                    <div class="event-header">
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <span class="event-type <?php echo $type_classes[$event['event_type']] ?? ''; ?>">
                                            <?php echo $type_labels[$event['event_type']] ?? $event['event_type']; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($event['description']): ?>
                                        <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="event-details">
                                        <div class="detail-row">
                                            <span class="detail-icon">⏰</span>
                                            <span><?php echo $event_date->format('l, F j, Y'); ?> at <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                        </div>
                                        
                                        <div class="detail-row">
                                            <span class="detail-icon">📍</span>
                                            <?php if ($event['meeting_link']): ?>
                                                <a href="<?php echo htmlspecialchars($event['meeting_link']); ?>" target="_blank" class="meeting-link">
                                                    Join Online Meeting →
                                                </a>
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars($event['location'] ?: 'Location TBD'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-stats">
                                        <div class="stat">
                                            <div class="stat-number"><?php echo $event['attending_count']; ?></div>
                                            <div class="stat-label">Attending</div>
                                        </div>
                                        <div class="stat">
                                            <div class="stat-number"><?php echo $event['total_attendees'] - $event['attending_count']; ?></div>
                                            <div class="stat-label">Maybe/No</div>
                                        </div>
                                    </div>
                                    
                                    <div class="rsvp-buttons">
                                        <button class="btn-rsvp attending <?php echo $user_response == 'attending' ? 'active' : ''; ?>" 
                                                onclick="updateRSVP(<?php echo $event['id']; ?>, 'attending')">
                                            ✅ Attending
                                        </button>
                                        <button class="btn-rsvp maybe <?php echo $user_response == 'maybe' ? 'active' : ''; ?>" 
                                                onclick="updateRSVP(<?php echo $event['id']; ?>, 'maybe')">
                                            🤔 Maybe
                                        </button>
                                        <button class="btn-rsvp not-attending <?php echo $user_response == 'not_attending' ? 'active' : ''; ?>" 
                                                onclick="updateRSVP(<?php echo $event['id']; ?>, 'not_attending')">
                                            ❌ Not Attending
                                        </button>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <span>Created by <?php echo htmlspecialchars($event['creator_name']); ?></span>
                                        <?php if ($current_user['role'] == 'team_leader' || $event['created_by'] == $current_user['id']): ?>
                                            <div class="event-actions">
                                                <button class="btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)">Delete</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Past Events -->
                    <?php if (!empty($past_events)): ?>
                        <div style="grid-column: 1/-1; margin: 24px 0 8px;">
                            <h2 style="font-size: 18px; font-weight: 600;">Past Events</h2>
                        </div>
                        <?php foreach ($past_events as $event): 
                            $event_date = new DateTime($event['event_date']);
                        ?>
                            <div class="event-card past">
                                <div class="event-date-banner" style="background: #666;">
                                    <div class="day"><?php echo $event_date->format('d'); ?></div>
                                    <div class="month"><?php echo $event_date->format('M'); ?></div>
                                </div>
                                
                                <div class="event-content">
                                    <div class="event-header">
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <span class="event-type <?php echo $type_classes[$event['event_type']] ?? ''; ?>">
                                            <?php echo $type_labels[$event['event_type']] ?? $event['event_type']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="event-details">
                                        <div class="detail-row">
                                            <span class="detail-icon">📅</span>
                                            <span><?php echo $event_date->format('F j, Y'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="event-stats">
                                        <div class="stat">
                                            <div class="stat-number"><?php echo $event['attending_count']; ?></div>
                                            <div class="stat-label">Attended</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Calendar View -->
            <div id="calendarView" class="calendar-view">
                <div class="calendar-header">
                    <button class="btn btn-outline" onclick="changeMonth(-1)">←</button>
                    <span class="calendar-month" id="currentMonth"><?php echo date('F Y'); ?></span>
                    <button class="btn btn-outline" onclick="changeMonth(1)">→</button>
                </div>
                
                <div class="calendar-weekdays">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                
                <div class="calendar-days" id="calendarDays"></div>
            </div>
        </main>
    </div>

    <!-- Create Event Modal -->
    <div id="createEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Event</h2>
                <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_event">
                
                <div class="form-group">
                    <label class="form-label">Event Title *</label>
                    <input type="text" name="title" class="form-input" required placeholder="e.g., Weekly Team Training">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Event Type *</label>
                    <select name="event_type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="training">Training</option>
                        <option value="opportunity">Opportunity Meeting</option>
                        <option value="team_meeting">Team Meeting</option>
                        <option value="product_presentation">Product Presentation</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="event_date" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input type="time" name="event_time" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-input" placeholder="Physical venue or address">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Meeting Link (Optional)</label>
                    <input type="url" name="meeting_link" class="form-input" placeholder="https://zoom.us/...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Event details, agenda, etc..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Event</h2>
                <button class="modal-close" onclick="closeModal('deleteEventModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this event? This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteEventForm">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="event_id" id="delete_event_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteEventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Day Events Modal -->
    <div id="dayEventsModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2 id="dayEventsTitle"></h2>
                <button class="modal-close" onclick="closeModal('dayEventsModal')">&times;</button>
            </div>
            
            <div id="dayEventsList" style="max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        let events = <?php echo json_encode($events); ?>;

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function showView(view) {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            if (view === 'grid') {
                document.getElementById('gridView').style.display = 'grid';
                document.getElementById('calendarView').classList.remove('show');
            } else {
                document.getElementById('gridView').style.display = 'none';
                document.getElementById('calendarView').classList.add('show');
                renderCalendar();
            }
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            document.getElementById('currentMonth').textContent = new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            let calendarHTML = '';
            
            for (let i = 0; i < firstDay; i++) {
                calendarHTML += '<div class="calendar-day empty"></div>';
            }
            
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const hasEvents = events.some(e => e.event_date === dateStr);
                
                calendarHTML += `<div class="calendar-day ${hasEvents ? 'has-event' : ''}" onclick="showDayEvents('${dateStr}')">
                    ${d}
                    ${hasEvents ? '<div class="event-indicator"></div>' : ''}
                </div>`;
            }
            
            document.getElementById('calendarDays').innerHTML = calendarHTML;
        }

        function changeMonth(delta) {
            currentDate.setMonth(currentDate.getMonth() + delta);
            renderCalendar();
        }

        function showDayEvents(date) {
            const dayEvents = events.filter(e => e.event_date === date);
            
            if (dayEvents.length === 0) return;
            
            const formattedDate = new Date(date).toLocaleDateString('en-US', { 
                weekday: 'long', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('dayEventsTitle').textContent = formattedDate;
            
            let eventsHTML = '';
            dayEvents.forEach(event => {
                const typeLabels = {
                    'training': 'Training',
                    'opportunity': 'Opportunity Meeting',
                    'team_meeting': 'Team Meeting',
                    'product_presentation': 'Product Presentation'
                };
                
                const typeClasses = {
                    'training': 'type-training',
                    'opportunity': 'type-opportunity',
                    'team_meeting': 'type-team_meeting',
                    'product_presentation': 'type-product_presentation'
                };
                
                eventsHTML += `
                    <div class="day-event-item" onclick="viewEvent(${event.id})">
                        <div class="event-time">${event.event_time ? new Date('2000-01-01T' + event.event_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Time TBD'}</div>
                        <div class="event-title">${event.title}</div>
                        <span class="event-type-badge ${typeClasses[event.event_type] || ''}">${typeLabels[event.event_type] || event.event_type}</span>
                    </div>
                `;
            });
            
            document.getElementById('dayEventsList').innerHTML = eventsHTML;
            openModal('dayEventsModal');
        }

        function viewEvent(eventId) {
            closeModal('dayEventsModal');
        }

        function updateRSVP(eventId, response) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="rsvp_event">
                <input type="hidden" name="event_id" value="${eventId}">
                <input type="hidden" name="response" value="${response}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function deleteEvent(eventId) {
            document.getElementById('delete_event_id').value = eventId;
            openModal('deleteEventModal');
        }

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