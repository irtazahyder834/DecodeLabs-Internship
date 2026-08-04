<?php
/**
 * Savoria — Project 3: Database Integration
 * config/db.php — PDO connection factory
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = env('DB_HOST', '127.0.0.1');
    $port    = env('DB_PORT', '3306');
    $dbname  = env('DB_NAME', 'savoria_project3');
    $user    = env('DB_USER', 'root');
    $pass    = env('DB_PASSWORD', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed.',
            'errors'  => ['db' => $e->getMessage()],
        ]);
        exit;
    }

    return $pdo;
}
