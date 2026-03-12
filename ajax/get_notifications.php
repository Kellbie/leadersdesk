<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized', 'notifications' => [], 'unread_count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

try {
    // Get notifications with proper linking
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND team_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id, $team_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? AND team_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id, $team_id]);
    $unread_count = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count
    ]);
} catch (Exception $e) {
    error_log("Error in get_notifications: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error',
        'notifications' => [],
        'unread_count' => 0
    ]);
}
?>