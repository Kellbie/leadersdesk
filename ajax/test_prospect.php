<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

echo "<h1>Prospect Debug Tool</h1>";

// Get a sample prospect
$stmt = $pdo->prepare("SELECT * FROM prospects WHERE team_id = ? LIMIT 1");
$stmt->execute([$current_user['team_id']]);
$sample_prospect = $stmt->fetch();

if (!$sample_prospect) {
    echo "<p style='color: orange;'>No prospects found. Please create a prospect first.</p>";
} else {
    echo "<h2>Sample Prospect Data:</h2>";
    echo "<pre>";
    print_r($sample_prospect);
    echo "</pre>";
    
    echo "<h2>Test AJAX URL:</h2>";
    $ajax_url = "ajax/get_prospect.php?id=" . $sample_prospect['id'];
    echo "<p>AJAX URL: <code>$ajax_url</code></p>";
    
    // Test the AJAX endpoint directly
    echo "<h2>Direct AJAX Test:</h2>";
    $ch = curl_init($ajax_url);
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
    
    echo "<p>HTTP Code: <strong>$httpCode</strong></p>";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "<p>Response: <pre>" . print_r($data, true) . "</pre></p>";
        } else {
            echo "<p>Raw Response: " . htmlspecialchars($response) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Failed to access AJAX endpoint</p>";
    }
}

echo "<h2>File Permissions:</h2>";
$files = [
    'ajax/get_prospect.php',
    'ajax/convert_prospect.php',
    'ajax/share_training.php',
    'ajax/update_prospect_stage.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        $readable = is_readable($file) ? '✅' : '❌';
        echo "<p>$file - Permissions: $perms $readable</p>";
    } else {
        echo "<p style='color: red;'>❌ $file does not exist!</p>";
    }
}
?>