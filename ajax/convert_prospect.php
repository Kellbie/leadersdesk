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
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

if (!$prospect_id) {
    echo json_encode(['success' => false, 'error' => 'Missing prospect ID']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Check if user has permission to convert this prospect
    if ($_SESSION['user_role'] == 'member') {
        $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND team_id = ? AND user_id = ?");
        $stmt->execute([$prospect_id, $team_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM prospects WHERE id = ? AND team_id = ?");
        $stmt->execute([$prospect_id, $team_id]);
    }
    
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prospect) {
        throw new Exception('Prospect not found or access denied');
    }
    
    // Check if prospect is already joined
    if ($prospect['stage'] == 'joined') {
        throw new Exception('This prospect has already been converted');
    }
    
    // Check if user already exists with this email
    if (!empty($prospect['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$prospect['email']]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            throw new Exception('A user with this email already exists');
        }
    }
    
    // Generate random password
    $temp_password = bin2hex(random_bytes(3)); // 6 characters
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Create user account
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, team_id, status) VALUES (?, ?, ?, ?, 'member', ?, 'active')");
    $stmt->execute([$prospect['name'], $prospect['email'], $prospect['phone'], $hashed_password, $team_id]);
    $new_user_id = $pdo->lastInsertId();
    
    // Create member profile
    $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, team_id, rank, member_type, join_date) VALUES (?, ?, 'Member', 'member', CURDATE())");
    $stmt->execute([$new_user_id, $team_id]);
    
    // Update prospect stage to 'joined'
    $stmt = $pdo->prepare("UPDATE prospects SET stage = 'joined' WHERE id = ?");
    $stmt->execute([$prospect_id]);
    
    // Log activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'convert_prospect', ?, 20)");
    $stmt->execute([$team_id, $user_id, "Converted prospect to member: {$prospect['name']}"]);
    
    // Notify team leader if current user is not leader
    if ($_SESSION['user_role'] != 'team_leader') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE team_id = ? AND role = 'team_leader'");
        $stmt->execute([$team_id]);
        $leader = $stmt->fetch();
        
        if ($leader) {
            $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, created_at) VALUES (?, ?, 'New Member Joined', ?, 'member', NOW())");
            $stmt->execute([$team_id, $leader['id'], "A prospect has been converted to member: {$prospect['name']}"]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Prospect converted to member successfully!',
        'password' => $temp_password,
        'user_id' => $new_user_id
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in convert_prospect: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in convert_prospect: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>