<?php
/**
 * Savoria — Project 4: Authentication & Authorization
 * lib/helpers.php — shared response and request helpers
 */

declare(strict_types=1);

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, string $message = 'OK', int $statusCode = 200): void
{
    json_response(['success' => true, 'message' => $message, 'data' => $data], $statusCode);
}

function json_error(string $message, int $statusCode = 400, array $errors = []): void
{
    json_response(['success' => false, 'message' => $message, 'errors' => $errors], $statusCode);
}

function get_request_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

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

function sanitize_string($value): string
{
    return trim(strip_tags((string) $value));
}
