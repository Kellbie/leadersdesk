<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

echo "<h1>Training Uploads Debug</h1>";

// Check uploads directory
$upload_dir = 'uploads/training/';
echo "<h2>Upload Directory: $upload_dir</h2>";

if (is_dir($upload_dir)) {
    echo "✅ Directory exists<br>";
    
    // Check if writable
    if (is_writable($upload_dir)) {
        echo "✅ Directory is writable<br>";
    } else {
        echo "❌ Directory is NOT writable<br>";
    }
    
    // List files
    $files = scandir($upload_dir);
    echo "<h3>Files in directory:</h3>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filepath = $upload_dir . $file;
            $size = filesize($filepath);
            echo "<li>$file (" . round($size / 1024, 2) . " KB)</li>";
        }
    }
    echo "</ul>";
} else {
    echo "❌ Directory does NOT exist<br>";
    // Try to create it
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

// Check database records
echo "<h2>Database Records</h2>";
$stmt = $pdo->prepare("SELECT id, title, file_path, content_type FROM trainings WHERE team_id = ?");
$stmt->execute([$current_user['team_id']]);
$trainings = $stmt->fetchAll();

if (empty($trainings)) {
    echo "No training records found.<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Title</th><th>Content Type</th><th>File Path</th><th>File Exists?</th></tr>";
    foreach ($trainings as $t) {
        $file_exists = file_exists($t['file_path']) ? '✅ Yes' : '❌ No';
        echo "<tr>";
        echo "<td>" . $t['id'] . "</td>";
        echo "<td>" . htmlspecialchars($t['title']) . "</td>";
        echo "<td>" . $t['content_type'] . "</td>";
        echo "<td>" . $t['file_path'] . "</td>";
        echo "<td>$file_exists</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Test a Specific File</h2>";
if (!empty($trainings)) {
    $test = $trainings[0];
    echo "Testing: " . $test['file_path'] . "<br>";
    echo "Full path: " . realpath($test['file_path']) . "<br>";
    echo "File exists: " . (file_exists($test['file_path']) ? 'Yes' : 'No') . "<br>";
    
    if (file_exists($test['file_path'])) {
        echo "<a href='" . htmlspecialchars($test['file_path']) . "' download>Download Test</a><br>";
        echo "<a href='" . htmlspecialchars($test['file_path']) . "' target='_blank'>View in Browser</a><br>";
    }
}
?>