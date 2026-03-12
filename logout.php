<?php
session_start();

// Log activity before logout if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['team_id'])) {
    require_once 'config/database.php';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'logout', 'User logged out', 0)");
        $stmt->execute([$_SESSION['team_id'], $_SESSION['user_id']]);
    } catch (Exception $e) {
        // Silently ignore logging errors on logout
    }
}

// Destroy session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login page without any message
header("Location: login.php");
exit();
?>