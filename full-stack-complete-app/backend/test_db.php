<?php
/**
 * Savoria — Full-Stack Complete App
 * backend/test_db.php — quick manual connectivity check
 *
 * Run: php test_db.php
 * (or visit it in a browser during local development)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

try {
    $pdo = get_db_connection();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "✅ Connected to database successfully.\n";
    echo "Tables found: " . implode(', ', $tables) . "\n";

    $counts = [];
    foreach (['users', 'categories', 'menu_items', 'orders', 'reservations'] as $table) {
        if (in_array($table, $tables, true)) {
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $counts[$table] = $count;
        }
    }

    echo "Row counts:\n";
    foreach ($counts as $table => $count) {
        echo "  - {$table}: {$count}\n";
    }
} catch (Throwable $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
