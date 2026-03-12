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
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login']);
    exit();
}

$prospect_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$user_role = $_SESSION['user_role'];

if (!$prospect_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid prospect ID']);
    exit();
}

try {
    // Check permission based on role
    if ($user_role == 'member') {
        $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
        $stmt->execute([$prospect_id, $team_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND team_id = ?");
        $stmt->execute([$prospect_id, $team_id]);
    }
    
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($prospect) {
        // Format dates for display
        if ($prospect['follow_up_date']) {
            $prospect['follow_up_date'] = date('Y-m-d', strtotime($prospect['follow_up_date']));
        }
        if ($prospect['created_at']) {
            $prospect['created_at'] = date('Y-m-d H:i:s', strtotime($prospect['created_at']));
        }
        
        echo json_encode(['success' => true, 'prospect' => $prospect]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Prospect not found or access denied']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in get_prospect: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in get_prospect: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>