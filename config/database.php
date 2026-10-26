
<?php
/**
 * Event-Pro - Database Connection
 * File: config/database.php
 */

// Local XAMPP database settings
$host = '127.0.0.1';
$dbname = 'event_pro';
$username = 'root';
$password = ''; // Default XAMPP password is usually empty

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log technical details privately; do not expose them to visitors.
    error_log('Event-Pro database connection failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Database connection failed. Please check the local configuration.');
}
