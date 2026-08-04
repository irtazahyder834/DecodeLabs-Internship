<?php
/**
 * Savoria — Project 3: Database Integration
 * config/env.php — minimal .env file loader (no external dependencies)
 */

declare(strict_types=1);

function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

load_env(__DIR__ . '/../.env');

function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false && $value !== null ? $value : $default;
}
