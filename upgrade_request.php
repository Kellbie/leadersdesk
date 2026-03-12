<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Only members can request upgrade
if ($current_user['role'] != 'member') {
    header("Location: dashboard.php");
    exit();
}

// Check if already requested
if ($current_user['upgrade_requested']) {
    $_SESSION['info_message'] = "You have already requested to upgrade. Please wait for approval.";
    header("Location: dashboard.php");
    exit();
}

// Instead of just requesting, redirect to registration for new team
header("Location: register.php?upgrade=1&name=" . urlencode($current_user['name']) . "&email=" . urlencode($current_user['email']) . "&phone=" . urlencode($current_user['phone']));
exit();
?>