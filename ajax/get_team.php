<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'super_admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$team_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid team ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($team) {
        echo json_encode(['success' => true, 'team' => $team]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>