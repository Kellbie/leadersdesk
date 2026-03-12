<?php
require_once 'config/database.php';

// Get the team name from the URL
// URL structure: kelto.tech/join.php?team=team-name
$team_slug = $_GET['team'] ?? '';

if (empty($team_slug)) {
    die("Invalid invite link.");
}

// Look up the team by its name
$stmt = $pdo->prepare("SELECT id, team_name FROM teams WHERE team_name = ?");
$stmt->execute([$team_slug]);
$team = $stmt->fetch();

if (!$team) {
    die("Team not found. Please check your invite link.");
}

// Store the team ID in the session to use after registration/login
$_SESSION['invite_team_id'] = $team['id'];

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, add them to the team
    $user_id = $_SESSION['user_id'];
    $team_id = $team['id'];

    // Check if user is already in this team
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND team_id = ?");
    $stmt->execute([$user_id, $team_id]);
    if ($stmt->fetch()) {
        // Already in the team, redirect to dashboard
        $_SESSION['success_message'] = "You are already a member of this team.";
        header("Location: dashboard.php");
        exit();
    }

    // Update the user's team_id
    $stmt = $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?");
    $stmt->execute([$team_id, $user_id]);

    // Create a member profile for them
    $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Member', 'member', CURDATE())");
    $stmt->execute([$user_id, $team_id]);

    // Log the activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'joined_via_link', 'Joined team via invite link', 5)");
    $stmt->execute([$team_id, $user_id]);
    
    // Send notification to team leader
    $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
    $stmt->execute([$team_id]);
    $leader = $stmt->fetch();
    
    if ($leader) {
        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Team Member Joined', ?, 'member', NOW())");
        $stmt->execute([$team_id, $leader['id'], "A new member has joined your team via invite link."]);
    }

    $_SESSION['success_message'] = "You have successfully joined the team " . htmlspecialchars($team['team_name']) . "!";
    header("Location: dashboard.php");
    exit();

} else {
    // User is not logged in, redirect them to the registration page with the team context
    header("Location: register.php?invite=1&team=" . urlencode($team['team_name']));
    exit();
}
?>