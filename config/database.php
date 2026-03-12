<?php
// Database configuration for Hostinger
define('DB_HOST', 'localhost'); // Hostinger uses localhost
define('DB_NAME', 'u936589048_desk');
define('DB_USER', 'u936589048_leader');
define('DB_PASS', 'Kella100200300400*');

// Define base URL for the application
define('BASE_URL', 'https://kelto.tech');
define('SITE_NAME', 'LeaderDesk');

// Function to get full URL
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+00:00'");
    
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch(PDOException $e) {
    // Log error instead of displaying it
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}
?>