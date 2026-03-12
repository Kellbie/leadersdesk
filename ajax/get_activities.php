<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$team_id = $_SESSION['team_id'];

try {
    $stmt = $pdo->prepare("
        SELECT al.*, u.name as user_name 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.id 
        WHERE al.team_id = ? 
        ORDER BY al.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$team_id, $limit, $offset]);
    $activities = $stmt->fetchAll();
    
    // Add time ago for each activity
    foreach ($activities as &$activity) {
        $activity['time_ago'] = time_elapsed_string($activity['created_at']);
    }
    
    // Check if there are more activities
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $total = $stmt->fetchColumn();
    
    $has_more = ($offset + $limit) < $total;
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'has_more' => $has_more,
        'page' => $page
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}
?>