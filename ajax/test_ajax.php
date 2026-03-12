<?php
require_once 'config/database.php';
session_start();

// Only allow access if logged in
if (!isset($_SESSION['user_id'])) {
    die('Please login first');
}

echo "<h1>AJAX Endpoint Test</h1>";

$endpoints = [
    'get_notifications.php',
    'get_prospect.php?id=1',
    'get_member_profile.php?id=1',
    'get_activities.php',
    'get_trainings.php'
];

echo "<h2>Testing AJAX Endpoints:</h2>";
echo "<ul>";

foreach ($endpoints as $endpoint) {
    $url = 'ajax/' . $endpoint;
    echo "<li><strong>$url</strong> - ";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    // Send session cookie
    $cookies = '';
    foreach ($_COOKIE as $name => $value) {
        $cookies .= "$name=$value; ";
    }
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                echo "<span style='color: green; font-weight: bold;'>✓ WORKING</span>";
            } else {
                echo "<span style='color: orange;'>✗ Error: " . htmlspecialchars($data['error'] ?? 'Unknown error') . "</span>";
            }
        } else {
            echo "<span style='color: orange;'>✗ Invalid JSON response</span>";
        }
    } else {
        echo "<span style='color: red;'>✗ HTTP $httpCode - Not accessible</span>";
    }
    
    echo "</li>";
}

echo "</ul>";

echo "<h2>Check File Permissions:</h2>";
echo "<ul>";
$ajax_dir = __DIR__ . '/ajax';
if (is_dir($ajax_dir)) {
    $files = scandir($ajax_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'php') {
            $filepath = $ajax_dir . '/' . $file;
            echo "<li>$file - " . substr(sprintf('%o', fileperms($filepath)), -4) . " - " . (is_readable($filepath) ? '✅ Readable' : '❌ Not readable') . "</li>";
        }
    }
} else {
    echo "<li style='color: red;'>❌ ajax directory not found!</li>";
}
echo "</ul>";
?>