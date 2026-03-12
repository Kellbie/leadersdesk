<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use absolute path
require_once dirname(__DIR__) . '/config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$prospect_id = isset($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : 0;
$training_id = isset($_POST['training_id']) ? (int)$_POST['training_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// Get user name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user ? $user['name'] : 'A team member';

if (!$prospect_id || !$training_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Verify prospect belongs to team and user has access
    if ($_SESSION['user_role'] == 'member') {
        $stmt = $pdo->prepare("SELECT p.*, u.name as prospect_name FROM prospects p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.team_id = ? AND p.user_id = ?");
        $stmt->execute([$prospect_id, $team_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, u.name as prospect_name FROM prospects p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.team_id = ?");
        $stmt->execute([$prospect_id, $team_id]);
    }
    
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prospect) {
        throw new Exception('Prospect not found');
    }
    
    // Verify training belongs to team
    $stmt = $pdo->prepare("SELECT id, title, content_type, file_path, content_url FROM trainings WHERE id = ? AND team_id = ?");
    $stmt->execute([$training_id, $team_id]);
    $training = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$training) {
        throw new Exception('Training not found');
    }
    
    // Save the share record
    $stmt = $pdo->prepare("INSERT INTO training_shares (prospect_id, shared_by, training_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$prospect_id, $user_id, $training_id, $message]);
    $share_id = $pdo->lastInsertId();
    
    // Create notification for the prospect owner (if different from sharer)
    if ($prospect['user_id'] != $user_id) {
        $notification_message = $user_name . " shared training material '" . $training['title'] . "' with your prospect " . $prospect['name'];
        $link = 'view_training_share.php?id=' . $share_id;
        $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, link, created_at) VALUES (?, ?, 'Training Shared', ?, 'training', ?, NOW())");
        $stmt->execute([$team_id, $prospect['user_id'], $notification_message, $link]);
    }
    
    // Log activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'shared_training', ?, 5)");
    $stmt->execute([$team_id, $user_id, "Shared training material '" . $training['title'] . "' with prospect " . $prospect['name']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Training shared successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in share_training: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in share_training: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>