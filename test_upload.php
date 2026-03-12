<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['test_file'])) {
    $upload_dir = 'uploads/training/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . $_FILES['test_file']['name'];
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['test_file']['tmp_name'], $target_file)) {
        $message = "File uploaded successfully to: $target_file";
        $file_url = $target_file;
    } else {
        $error = "Upload failed. Error: " . $_FILES['test_file']['error'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Upload</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test File Upload</h1>
    
    <?php if (isset($message)): ?>
        <p class="success"><?php echo $message; ?></p>
        <?php if (isset($file_url)): ?>
            <p><a href="<?php echo $file_url; ?>" download>Download Test File</a></p>
            <p><a href="<?php echo $file_url; ?>" target="_blank">View in Browser</a></p>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" required>
        <button type="submit">Upload Test</button>
    </form>
    
    <h2>Check Upload Directory</h2>
    <?php
    $upload_dir = 'uploads/training/';
    if (is_dir($upload_dir)) {
        echo "<p>✅ Directory exists</p>";
        echo "<p>Writable: " . (is_writable($upload_dir) ? '✅ Yes' : '❌ No') . "</p>";
        
        $files = scandir($upload_dir);
        echo "<h3>Files:</h3>";
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $upload_dir . $file;
                echo "<li>$file (" . round(filesize($path)/1024, 2) . " KB) - <a href='$path' download>Download</a></li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Directory does not exist</p>";
    }
    ?>
</body>
</html>