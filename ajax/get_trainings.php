<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$team_id = $_SESSION['team_id'];

try {
    $stmt = $pdo->prepare("SELECT id, title, description, content_type, content_url, file_path FROM trainings WHERE team_id = ? ORDER BY created_at DESC");
    $stmt->execute([$team_id]);
    $trainings = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'trainings' => $trainings]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>