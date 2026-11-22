<?php
// config/db.php
// Shared PDO connection — include this in every PHP file that needs the DB

define('DB_HOST', 'localhost');
define('DB_NAME', 'pest_monitor');   // ⚠️ change if different
define('DB_USER', 'root');            // ⚠️ change to your MySQL user
define('DB_PASS', '');                // ⚠️ change to your MySQL password

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
}
