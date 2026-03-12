<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Super Admin check
if ($current_user['role'] != 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$file = $_GET['file'] ?? '';
$backup_dir = __DIR__ . '/backups/';
$file_path = $backup_dir . basename($file);

// Security check - ensure file is in backups directory
if (file_exists($file_path) && strpos(realpath($file_path), realpath($backup_dir)) === 0) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
} else {
    header("Location: admin_settings.php?error=Invalid file");
    exit();
}
?>