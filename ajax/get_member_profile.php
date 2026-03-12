<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team_id = $_SESSION['team_id'];

if ($member_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.status,
                   mp.rank, mp.member_type, mp.activity_score, mp.total_recruits, mp.total_sales, mp.join_date,
                   (SELECT COUNT(*) FROM member_profiles WHERE upline_user_id = u.id) as downline_count
            FROM users u
            LEFT JOIN member_profiles mp ON u.id = mp.user_id
            WHERE u.id = ? AND u.team_id = ?
        ");
        $stmt->execute([$member_id, $team_id]);
        $member = $stmt->fetch();
        
        if ($member) {
            echo json_encode(['success' => true, 'member' => $member]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Member not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid member ID']);
}
?>