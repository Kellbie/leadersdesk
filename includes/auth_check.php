<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$stmt = $pdo->prepare("SELECT u.*, t.team_name, t.subscription_status, tb.primary_color, tb.logo_url, tb.tagline 
                       FROM users u 
                       LEFT JOIN teams t ON u.team_id = t.id 
                       LEFT JOIN team_branding tb ON u.team_id = tb.team_id 
                       WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Check team subscription status for team leaders (not for super_admin)
if ($current_user['role'] != 'super_admin' && $current_user['role'] == 'team_leader' && $current_user['subscription_status'] == 'expired') {
    header("Location: subscription_expired.php");
    exit();
}

// Super admin doesn't need team_id for some pages
if ($current_user['role'] == 'super_admin' && !isset($current_user['team_id'])) {
    // Super admin might not have a team, that's ok
}
?>