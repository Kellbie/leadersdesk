<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$prospect_id = isset($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : 0;
$stage = isset($_POST['stage']) ? $_POST['stage'] : '';
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

if (!$prospect_id || !$stage) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    // Check if user has permission to update this prospect
    if ($_SESSION['user_role'] == 'member') {
        $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
        $stmt->execute([$prospect_id, $team_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM prospects WHERE id = ? AND team_id = ?");
        $stmt->execute([$prospect_id, $team_id]);
    }
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to update this prospect']);
        exit();
    }
    
    $stmt = $pdo->prepare("UPDATE prospects SET stage = ? WHERE id = ? AND team_id = ?");
    $stmt->execute([$stage, $prospect_id, $team_id]);
    
    // Award points for stage progression
    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'update_prospect_stage', ?, 3)");
    $stmt->execute([$team_id, $user_id, "Updated prospect stage to $stage"]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error in update_prospect_stage: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>