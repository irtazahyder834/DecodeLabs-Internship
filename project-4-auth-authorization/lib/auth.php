<?php
/**
 * Savoria — Project 4: Authentication & Authorization
 * lib/auth.php — bearer token extraction + RBAC middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/jwt.php';

function get_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? apache_request_headers()['Authorization']
        ?? null;

    if (!$header || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return null;
    }
    return trim($matches[1]);
}

/**
 * Require a valid JWT on the request. Halts the request with 401 if
 * missing/invalid/expired. Returns the decoded token payload (contains
 * user id, email, and role) on success.
 */
function require_auth(): array
{
    $token = get_bearer_token();
    if (!$token) {
        json_error('Authentication required. Include an Authorization: Bearer <token> header.', 401);
    }

    $payload = jwt_decode($token, env('JWT_SECRET', ''));
    if (!$payload) {
        json_error('Invalid or expired token.', 401);
    }

    return $payload;
}

/**
 * Require the authenticated user to hold one of the given roles.
 * Must be called after require_auth(). Halts with 403 if unauthorized.
 */
function require_role(array $currentUser, array $allowedRoles): void
{
    if (!in_array($currentUser['role'] ?? '', $allowedRoles, true)) {
        json_error(
            'You do not have permission to perform this action.',
            403,
            ['required_role' => $allowedRoles]
        );
    }
}
