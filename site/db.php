<?php
require_once __DIR__ . '/config.php';

// utf8mb4, niet utf8mb3: het bord kan emoticons bevatten (4-byte UTF-8).
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Databaseverbinding mislukt: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
