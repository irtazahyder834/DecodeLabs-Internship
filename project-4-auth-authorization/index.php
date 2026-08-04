<?php
/**
 * Savoria — Project 4: Authentication & Authorization
 * index.php — front controller / router (JWT auth + RBAC)
 *
 * Routes:
 *   POST /auth/register           Create a customer account
 *   POST /auth/login              Authenticate and receive a JWT
 *   GET  /auth/me                 Get the current authenticated user     (any role)
 *   POST /auth/logout             Client-side token discard instruction
 *
 *   GET  /staff/dashboard         Staff-only demo route                  (staff, admin)
 *   GET  /admin/users             List all users                         (admin only)
 *   PATCH /admin/users/{id}/role  Change a user's role                   (admin only)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/jwt.php';
require_once __DIR__ . '/lib/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$path = get_route_path();
$segments = array_values(array_filter(explode('/', $path)));

if ($path === '/' && $method === 'GET') {
    json_success(['name' => 'Savoria Auth & Authorization API', 'version' => '1.0.0'], 'API is running.');
}

// ---------------------------------------------------------
// POST /auth/register
// ---------------------------------------------------------
if ($path === '/auth/register' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_register_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $check->execute([':email' => strtolower(trim($payload['email']))]);
    if ($check->fetch()) {
        json_error('An account with this email already exists.', 409);
    }

    // New public signups are always created as 'customer' — role escalation
    // must go through an authenticated admin via PATCH /admin/users/{id}/role.
    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, email, phone, password_hash, role)
        VALUES (:full_name, :email, :phone, :password_hash, :role)
    ');
    $stmt->execute([
        ':full_name'     => sanitize_string($payload['full_name']),
        ':email'         => strtolower(trim($payload['email'])),
        ':phone'         => $payload['phone'] ?? null,
        ':password_hash' => password_hash($payload['password'], PASSWORD_BCRYPT),
        ':role'          => 'customer',
    ]);

    $userId = (int) $pdo->lastInsertId();
    json_success(['id' => $userId, 'email' => $payload['email'], 'role' => 'customer'], 'Account created. You can now log in.', 201);
}

// ---------------------------------------------------------
// POST /auth/login
// ---------------------------------------------------------
if ($path === '/auth/login' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_login_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1');
    $stmt->execute([':email' => strtolower(trim($payload['email']))]);
    $user = $stmt->fetch();

    // Use a generic error message for both "no such user" and "wrong password"
    // to avoid leaking which emails are registered.
    if (!$user || !password_verify($payload['password'], $user['password_hash'])) {
        json_error('Invalid email or password.', 401);
    }

    $token = jwt_encode(
        [
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'iss'   => env('JWT_ISSUER', 'savoria-api'),
        ],
        env('JWT_SECRET', ''),
        (int) env('JWT_EXPIRY_SECONDS', 3600)
    );

    json_success([
        'token'      => $token,
        'token_type' => 'Bearer',
        'expires_in' => (int) env('JWT_EXPIRY_SECONDS', 3600),
        'user'       => [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ],
    ], 'Login successful.');
}

// ---------------------------------------------------------
// GET /auth/me — any authenticated role
// ---------------------------------------------------------
if ($path === '/auth/me' && $method === 'GET') {
    $currentUser = require_auth();

    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUser['sub']]);
    $user = $stmt->fetch();

    if (!$user) json_error('User no longer exists.', 404);
    json_success($user);
}

// ---------------------------------------------------------
// POST /auth/logout
// ---------------------------------------------------------
if ($path === '/auth/logout' && $method === 'POST') {
    require_auth();
    // JWTs are stateless: the API cannot invalidate a token that has already
    // been issued. The client is responsible for discarding it. For true
    // server-side revocation, issue short-lived access tokens paired with
    // the refresh_tokens table in the schema and revoke on logout.
    json_success(null, 'Logged out. Discard the token on the client.');
}

// ---------------------------------------------------------
// GET /staff/dashboard — staff + admin
// ---------------------------------------------------------
if ($path === '/staff/dashboard' && $method === 'GET') {
    $currentUser = require_auth();
    require_role($currentUser, ['staff', 'admin']);

    $totalUsers = (int) $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];

    json_success([
        'welcome'      => 'Staff dashboard access granted for role: ' . $currentUser['role'],
        'total_users'  => $totalUsers,
    ]);
}

// ---------------------------------------------------------
// GET /admin/users — admin only
// ---------------------------------------------------------
if ($path === '/admin/users' && $method === 'GET') {
    $currentUser = require_auth();
    require_role($currentUser, ['admin']);

    $stmt = $pdo->query('SELECT id, full_name, email, phone, role, is_active, created_at FROM users ORDER BY created_at DESC');
    json_success($stmt->fetchAll());
}

// ---------------------------------------------------------
// PATCH /admin/users/{id}/role — admin only
// ---------------------------------------------------------
if (($segments[0] ?? '') === 'admin' && ($segments[1] ?? '') === 'users' && isset($segments[2]) && ($segments[3] ?? '') === 'role' && $method === 'PATCH') {
    $currentUser = require_auth();
    require_role($currentUser, ['admin']);

    $targetId = (int) $segments[2];
    $payload = get_request_body();
    $validRoles = ['customer', 'staff', 'admin'];

    if (empty($payload['role']) || !in_array($payload['role'], $validRoles, true)) {
        json_error('A valid role is required: ' . implode(', ', $validRoles), 422);
    }

    if ($targetId === (int) $currentUser['sub'] && $payload['role'] !== 'admin') {
        json_error('You cannot demote your own account.', 400);
    }

    $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute([':role' => $payload['role'], ':id' => $targetId]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM users WHERE id = :id');
        $check->execute([':id' => $targetId]);
        if (!$check->fetch()) json_error('User not found.', 404);
    }

    json_success(['id' => $targetId, 'role' => $payload['role']], 'User role updated.');
}

json_error("Route {$path} not found.", 404);
