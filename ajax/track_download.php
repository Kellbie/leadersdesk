<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$share_id = isset($_POST['share_id']) ? (int)$_POST['share_id'] : 0;

if (!$share_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid share ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE training_shares SET downloaded_at = NOW() WHERE id = ?");
    $stmt->execute([$share_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>