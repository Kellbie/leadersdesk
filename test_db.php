<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

// Your database credentials
$host = 'localhost';
$dbname = 'u936589048_desk';
$username = 'u936589048_leader';
$password = 'Kella100200400*';

echo "<p>Testing connection with:</p>";
echo "<ul>";
echo "<li>Host: " . $host . "</li>";
echo "<li>Database: " . $dbname . "</li>";
echo "<li>Username: " . $username . "</li>";
echo "<li>Password: [hidden]</li>";
echo "</ul>";

try {
    // Test connection without selecting database
    $pdo = new PDO("mysql:host=" . $host, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ MySQL connection successful!</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    $db_exists = $stmt->fetch();
    
    if ($db_exists) {
        echo "<p style='color: green;'>✅ Database '$dbname' exists!</p>";
        
        // Try to connect to the specific database
        $pdo2 = new PDO("mysql:host=" . $host . ";dbname=" . $dbname, $username, $password);
        $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color: green;'>✅ Successfully connected to database '$dbname'!</p>";
        
        // Check if tables exist
        $stmt = $pdo2->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "<p style='color: green;'>✅ Database has " . count($tables) . " tables.</p>";
            echo "<p>Tables found: " . implode(", ", $tables) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Database exists but has no tables. You need to import your SQL file.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Database '$dbname' does NOT exist!</p>";
        echo "<p>You need to create the database in Hostinger cPanel.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    
    // Give specific troubleshooting advice
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p><strong>🔑 Access denied:</strong> Your username or password is incorrect.</p>";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p><strong>📁 Unknown database:</strong> The database doesn't exist. Create it in cPanel.</p>";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<p><strong>🌐 Connection refused:</strong> Host might be wrong. Try using '127.0.0.1' instead of 'localhost'.</p>";
    }
}
?>