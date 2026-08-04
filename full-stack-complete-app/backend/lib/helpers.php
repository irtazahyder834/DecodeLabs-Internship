<?php
/**
 * Savoria — Full-Stack Complete App
 * backend/lib/helpers.php — shared response and request helpers
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

/**
 * Normalize a reservation time string into MySQL TIME format (HH:MM:SS).
 * Accepts both 24-hour ("19:30") and 12-hour with AM/PM ("7:30 PM") input,
 * since the frontend's <select> sends the latter. Returns null if the
 * string cannot be parsed into a valid time.
 */
function normalize_reservation_time(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;

    // 24-hour "HH:MM" or "HH:MM:SS"
    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $raw, $m)) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], (int)($m[4] ?? 0));
    }

    // 12-hour "H:MM AM/PM"
    if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)\s*(AM|PM)$/i', $raw, $m)) {
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $meridiem = strtoupper($m[3]);
        if ($meridiem === 'PM' && $hour !== 12) $hour += 12;
        if ($meridiem === 'AM' && $hour === 12) $hour = 0;
        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    return null;
}

/**
 * Attempt to resolve the current user from an Authorization header
 * without forcing authentication — used on routes like POST /orders
 * that work for both guests and logged-in customers.
 */
function get_optional_user(): ?array
{
    $token = get_bearer_token();
    if (!$token) return null;
    return jwt_decode($token, env_required_secret('JWT_SECRET'));
}
