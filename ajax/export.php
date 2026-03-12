<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$team_id = $_SESSION['team_id'];

if ($type == 'team') {
    // Export team members
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.phone, u.status, mp.rank, mp.activity_score, mp.total_recruits, mp.total_sales, mp.join_date
        FROM users u
        LEFT JOIN member_profiles mp ON u.id = mp.user_id
        WHERE u.team_id = ? AND u.role = 'member'
        ORDER BY u.name
    ");
    $stmt->execute([$team_id]);
    $data = $stmt->fetchAll();
    
    $filename = 'team_export_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Phone', 'Status', 'Rank', 'Activity Score', 'Recruits', 'Sales', 'Joined']);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    
} elseif ($type == 'prospects') {
    // Export prospects
    $stmt = $pdo->prepare("SELECT * FROM prospects WHERE team_id = ? ORDER BY created_at DESC");
    $stmt->execute([$team_id]);
    $data = $stmt->fetchAll();
    
    $filename = 'prospects_export_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Phone', 'Email', 'Source', 'Stage', 'Follow-up Date', 'Notes', 'Created']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['name'],
            $row['phone'],
            $row['email'],
            $row['source'],
            $row['stage'],
            $row['follow_up_date'],
            $row['notes'],
            $row['created_at']
        ]);
    }
    fclose($output);
    
} else {
    die('Invalid export type');
}
?>