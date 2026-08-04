<?php
/**
 * Savoria — Project 2: Backend API (JSON storage)
 * lib/helpers.php — shared response, file I/O, and utility helpers
 */

declare(strict_types=1);

/**
 * Send a JSON response and terminate the script.
 */
function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, string $message = 'OK', int $statusCode = 200): void
{
    json_response([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], $statusCode);
}

function json_error(string $message, int $statusCode = 400, array $errors = []): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors'  => $errors,
    ], $statusCode);
}

/**
 * Read and decode a JSON data file. Returns an empty array if missing/invalid.
 */
function read_json_file(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Encode and atomically write data back to a JSON file using a file lock
 * to avoid corrupting the store under concurrent requests.
 */
function write_json_file(string $path, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $fp = fopen($path, 'c+');
    if (!$fp) {
        return false;
    }
    $success = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $success = fwrite($fp, $json) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $success;
}

/**
 * Generate the next auto-increment style ID for a JSON "table".
 */
function next_id(array $items): int
{
    if (empty($items)) {
        return 1;
    }
    $ids = array_column($items, 'id');
    return max($ids) + 1;
}

/**
 * Parse the JSON request body into an associative array.
 */
function get_request_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Return the request path relative to this script, with query string and
 * trailing slash stripped, e.g. "/menu/3".
 */
function get_route_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '//' ? '/' : $uri;
}
