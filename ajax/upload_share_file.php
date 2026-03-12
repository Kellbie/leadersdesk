<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$prospect_id = isset($_POST['prospect_id']) ? (int)$_POST['prospect_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

if (!$prospect_id) {
    echo json_encode(['success' => false, 'error' => 'Missing prospect ID']);
    exit();
}

try {
    // Verify prospect belongs to team and user has access
    if ($_SESSION['user_role'] == 'member') {
        $stmt = $pdo->prepare("SELECT p.*, u.name as prospect_name FROM prospects p WHERE p.id = ? AND p.team_id = ? AND p.user_id = ?");
        $stmt->execute([$prospect_id, $team_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, u.name as prospect_name FROM prospects p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.team_id = ?");
        $stmt->execute([$prospect_id, $team_id]);
    }
    
    $prospect = $stmt->fetch();
    
    if (!$prospect) {
        throw new Exception('Prospect not found');
    }
    
    // Handle file upload
    if (!isset($_FILES['share_file']) || $_FILES['share_file']['error'] != 0) {
        throw new Exception('Please select a file to share');
    }
    
    $upload_dir = '../uploads/shared/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['share_file'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'zip'];
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_types));
    }
    
    // Validate file size (max 50MB)
    if ($file_size > 50 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 50MB.');
    }
    
    // Generate unique filename
    $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = 'uploads/shared/' . $new_file_name;
    $full_path = '../' . $file_path;
    
    if (move_uploaded_file($file_tmp, $full_path)) {
        // Save to database
        $stmt = $pdo->prepare("INSERT INTO prospect_shared_files (prospect_id, shared_by, file_name, file_path, file_size, file_type, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$prospect_id, $user_id, $file_name, $file_path, $file_size, $file_ext, $message]);
        $share_id = $pdo->lastInsertId();
        
        // Create notification for the prospect owner (if different from sharer)
        if ($prospect['user_id'] != $user_id) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $sharer = $stmt->fetch();
            $sharer_name = $sharer ? $sharer['name'] : 'A team member';
            
            $notification_message = $sharer_name . " shared a file '" . $file_name . "' with your prospect " . $prospect['prospect_name'];
            $link = 'view_shared_file.php?id=' . $share_id;
            
            $stmt = $pdo->prepare("INSERT INTO notifications (team_id, user_id, title, message, type, link, created_at) VALUES (?, ?, 'File Shared', ?, 'training', ?, NOW())");
            $stmt->execute([$team_id, $prospect['user_id'], $notification_message, $link]);
        }
        
        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'shared_file', ?, 5)");
        $stmt->execute([$team_id, $user_id, "Shared file '" . $file_name . "' with prospect " . $prospect['prospect_name']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'File shared successfully!',
            'file_name' => $file_name,
            'share_id' => $share_id
        ]);
    } else {
        throw new Exception('Failed to upload file');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>