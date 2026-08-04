<?php
/**
 * Savoria — Full-Stack Complete App
 * backend/index.php — front controller / router
 *
 * Combines menu browsing, ordering, table reservations, and JWT
 * authentication with role-based access control into one API.
 *
 * ── Public ──────────────────────────────────────────────
 *   GET    /categories
 *   GET    /menu-items                 (?category_id=, ?available_only=true)
 *   GET    /menu-items/{id}
 *   POST   /auth/register
 *   POST   /auth/login
 *   POST   /auth/forgot-password
 *   POST   /auth/reset-password
 *   POST   /reservations               (rejected with 409 if the time slot is full)
 *   GET    /orders/{id}                (guest: requires ?phone= matching the order)
 *
 * ── Customer / guest (orders work for both) ────────────
 *   POST   /orders                     (attaches user_id if a valid token is sent)
 *   GET    /auth/me                    (auth required)
 *   POST   /auth/change-password       (auth required)
 *   GET    /my/orders                  (auth required — current user's orders)
 *   POST   /auth/logout                (auth required)
 *
 * ── Staff & Admin ───────────────────────────────────────
 *   GET    /orders                     (staff, admin)
 *   PATCH  /orders/{id}                (staff, admin)
 *   GET    /reservations               (staff, admin)
 *   PATCH  /reservations/{id}          (staff, admin)
 *   GET    /admin/summary              (staff, admin — today's orders/revenue, pending counts)
 *
 * ── Admin only ──────────────────────────────────────────
 *   POST   /menu-items
 *   PUT    /menu-items/{id}
 *   DELETE /menu-items/{id}
 *   GET    /admin/users
 *   PATCH  /admin/users/{id}/role
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/jwt.php';
require_once __DIR__ . '/lib/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$path = get_route_path();
$segments = array_values(array_filter(explode('/', $path)));

/* Active promo codes: code => ['type' => 'percent'|'flat', 'value' => number, 'label' => string] */
const PROMO_CODES = [
    'SAVORIA10'  => ['type' => 'percent', 'value' => 10, 'label' => '10% off'],
    'WELCOME100' => ['type' => 'flat',    'value' => 100, 'label' => 'Rs. 100 off'],
];

function resolve_promo_discount(?string $code, float $subtotal): array
{
    if (!$code) return [null, 0.0];
    $code = strtoupper(trim($code));
    if (!isset(PROMO_CODES[$code])) {
        throw new RuntimeException("Promo code \"$code\" is not valid.");
    }
    $promo = PROMO_CODES[$code];
    $discount = $promo['type'] === 'percent'
        ? round($subtotal * ($promo['value'] / 100), 2)
        : (float) $promo['value'];
    $discount = min($discount, $subtotal); // never discount below zero
    return [$code, $discount];
}

if ($path === '/' && $method === 'GET') {
    json_success(['name' => 'Savoria Full-Stack API', 'version' => '1.0.0'], 'API is running.');
}

/* =========================================================
   AUTH
   ========================================================= */

if ($path === '/auth/register' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_register_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $check->execute([':email' => strtolower(trim($payload['email']))]);
    if ($check->fetch()) json_error('An account with this email already exists.', 409);

    $stmt = $pdo->prepare('
        INSERT INTO users (full_name, email, phone, password_hash, role)
        VALUES (:full_name, :email, :phone, :password_hash, "customer")
    ');
    $stmt->execute([
        ':full_name'     => sanitize_string($payload['full_name']),
        ':email'         => strtolower(trim($payload['email'])),
        ':phone'         => $payload['phone'] ?? null,
        ':password_hash' => password_hash($payload['password'], PASSWORD_BCRYPT),
    ]);

    json_success(['id' => (int) $pdo->lastInsertId()], 'Account created. You can now log in.', 201);
}

if ($path === '/auth/login' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_login_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1');
    $stmt->execute([':email' => strtolower(trim($payload['email']))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($payload['password'], $user['password_hash'])) {
        json_error('Invalid email or password.', 401);
    }

    $token = jwt_encode(
        ['sub' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'iss' => env('JWT_ISSUER', 'savoria-api')],
        env_required_secret('JWT_SECRET'),
        (int) env('JWT_EXPIRY_SECONDS', 3600)
    );

    json_success([
        'token'      => $token,
        'token_type' => 'Bearer',
        'expires_in' => (int) env('JWT_EXPIRY_SECONDS', 3600),
        'user'       => ['id' => $user['id'], 'full_name' => $user['full_name'], 'email' => $user['email'], 'role' => $user['role']],
    ], 'Login successful.');
}

if ($path === '/auth/me' && $method === 'GET') {
    $currentUser = require_auth();
    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUser['sub']]);
    $user = $stmt->fetch();
    if (!$user) json_error('User no longer exists.', 404);
    json_success($user);
}

if ($path === '/auth/logout' && $method === 'POST') {
    require_auth();
    json_success(null, 'Logged out. Discard the token on the client.');
}

if ($path === '/auth/change-password' && $method === 'POST') {
    $currentUser = require_auth();
    $payload = get_request_body();
    [$isValid, $errors] = validate_change_password_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUser['sub']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($payload['current_password'], $user['password_hash'])) {
        json_error('Current password is incorrect.', 401);
    }

    $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $update->execute([
        ':hash' => password_hash($payload['new_password'], PASSWORD_BCRYPT),
        ':id'   => $user['id'],
    ]);
    json_success(null, 'Password changed successfully.');
}

/**
 * Forgot password: always responds with a generic success message
 * regardless of whether the email exists, to avoid leaking which
 * emails are registered. No email service is wired up in this
 * project, so — for local dev/testing only — the raw reset link is
 * included in the response when APP_DEBUG is enabled. In production
 * this token would be emailed to the user instead of returned here.
 */
if ($path === '/auth/forgot-password' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_forgot_password_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $email = strtolower(trim($payload['email']));
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND is_active = 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    $response = ['message' => 'If an account with that email exists, a password reset link has been sent.'];

    if ($user) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

        $insert = $pdo->prepare('
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ');
        $insert->execute([':user_id' => $user['id'], ':token_hash' => $tokenHash, ':expires_at' => $expiresAt]);

        if (filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN)) {
            $response['dev_reset_token'] = $rawToken;
            $response['dev_note'] = 'APP_DEBUG is on, so the token is returned here instead of emailed. Set APP_DEBUG=false in production.';
        }
    }

    json_success($response, $response['message']);
}

if ($path === '/auth/reset-password' && $method === 'POST') {
    $payload = get_request_body();
    [$isValid, $errors] = validate_reset_password_payload($payload);
    if (!$isValid) json_error('Validation failed.', 422, $errors);

    $tokenHash = hash('sha256', (string) $payload['token']);
    $stmt = $pdo->prepare('
        SELECT * FROM password_resets
        WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([':hash' => $tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) json_error('This reset link is invalid or has expired. Please request a new one.', 400);

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $update->execute([':hash' => password_hash($payload['password'], PASSWORD_BCRYPT), ':id' => $reset['user_id']]);

        $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $markUsed->execute([':id' => $reset['id']]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        json_error('Could not reset password due to a server error.', 500);
    }

    json_success(null, 'Password has been reset. You can now log in with your new password.');
}

/* =========================================================
   CATEGORIES (public, read-only here)
   ========================================================= */

if ($path === '/categories' && $method === 'GET') {
    $stmt = $pdo->query('SELECT id, name, description, sort_order FROM categories ORDER BY sort_order ASC');
    json_success($stmt->fetchAll());
}

/* =========================================================
   MENU ITEMS
   ========================================================= */

if (($segments[0] ?? '') === 'menu-items') {

    if ($method === 'GET' && isset($segments[1])) {
        $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt->execute([':id' => (int) $segments[1]]);
        $item = $stmt->fetch();
        if (!$item) json_error('Menu item not found.', 404);
        json_success($item);
    }

    if ($method === 'GET') {
        $sql = 'SELECT m.*, c.name AS category_name FROM menu_items m JOIN categories c ON c.id = m.category_id WHERE 1=1';
        $params = [];
        if (!empty($_GET['category_id'])) { $sql .= ' AND m.category_id = :category_id'; $params[':category_id'] = (int)$_GET['category_id']; }
        if (($_GET['available_only'] ?? '') === 'true') { $sql .= ' AND m.is_available = 1'; }
        $sql .= ' ORDER BY m.category_id, m.name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_success($stmt->fetchAll());
    }

    // Admin-only write operations
    if ($method === 'POST') {
        $currentUser = require_auth();
        require_role($currentUser, ['admin']);

        $payload = get_request_body();
        [$isValid, $errors] = validate_menu_item_payload($payload);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        $stmt = $pdo->prepare('
            INSERT INTO menu_items (category_id, name, description, price, image_url, spice_level, prep_time_minutes, is_available)
            VALUES (:category_id, :name, :description, :price, :image_url, :spice_level, :prep_time_minutes, :is_available)
        ');
        $stmt->execute([
            ':category_id' => (int)$payload['category_id'], ':name' => sanitize_string($payload['name']),
            ':description' => sanitize_string($payload['description'] ?? ''), ':price' => (float)$payload['price'],
            ':image_url' => $payload['image_url'] ?? null, ':spice_level' => (int)($payload['spice_level'] ?? 0),
            ':prep_time_minutes' => (int)($payload['prep_time_minutes'] ?? 15),
            ':is_available' => isset($payload['is_available']) ? (bool)$payload['is_available'] : true,
        ]);
        $stmt2 = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt2->execute([':id' => (int) $pdo->lastInsertId()]);
        json_success($stmt2->fetch(), 'Menu item created.', 201);
    }

    if ($method === 'PUT' && isset($segments[1])) {
        $currentUser = require_auth();
        require_role($currentUser, ['admin']);

        $id = (int) $segments[1];
        $payload = get_request_body();
        [$isValid, $errors] = validate_menu_item_payload($payload, true);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        $fields = []; $params = [':id' => $id];
        foreach (['category_id','name','description','price','image_url','spice_level','prep_time_minutes','is_available'] as $f) {
            if (array_key_exists($f, $payload)) { $fields[] = "$f = :$f"; $params[":$f"] = $payload[$f]; }
        }
        if (empty($fields)) json_error('No updatable fields provided.', 422);

        $stmt = $pdo->prepare('UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);

        $stmt2 = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt2->execute([':id' => $id]);
        $item = $stmt2->fetch();
        if (!$item) json_error('Menu item not found.', 404);
        json_success($item, 'Menu item updated.');
    }

    if ($method === 'DELETE' && isset($segments[1])) {
        $currentUser = require_auth();
        require_role($currentUser, ['admin']);

        $id = (int) $segments[1];
        $stmt = $pdo->prepare('DELETE FROM menu_items WHERE id = :id');
        try {
            $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            json_error('Cannot delete this item — it is referenced by existing orders.', 409);
        }
        if ($stmt->rowCount() === 0) json_error('Menu item not found.', 404);
        json_success(null, 'Menu item deleted.');
    }

    json_error('Method not allowed on /menu-items.', 405);
}

/* =========================================================
   ORDERS
   ========================================================= */

if (($segments[0] ?? '') === 'orders') {

    if ($method === 'GET' && isset($segments[1])) {
        // Guest order tracking: requires either a valid auth token (owner,
        // staff, or admin) or the phone number the order was placed under,
        // so order details (name, address, items, total) can't be scraped
        // by simply guessing sequential order IDs.
        $id = (int) $segments[1];
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();
        if (!$order) json_error('Order not found.', 404);

        $optionalUser = get_optional_user();
        $isOwnerOrStaff = $optionalUser && (
            (int)($optionalUser['sub'] ?? 0) === (int)$order['user_id']
            || in_array($optionalUser['role'] ?? '', ['staff', 'admin'], true)
        );

        if (!$isOwnerOrStaff) {
            $suppliedPhone = preg_replace('/\D/', '', (string) ($_GET['phone'] ?? ''));
            $orderPhone = preg_replace('/\D/', '', (string) $order['phone']);
            if ($suppliedPhone === '' || $suppliedPhone !== $orderPhone) {
                json_error('To track this order, include the phone number it was placed under (?phone=).', 403);
            }
        }

        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $itemsStmt->execute([':id' => $id]);
        $order['items'] = $itemsStmt->fetchAll();
        json_success($order);
    }

    if ($method === 'GET') {
        $currentUser = require_auth();
        require_role($currentUser, ['staff', 'admin']);
        $stmt = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC');
        json_success($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $optionalUser = get_optional_user(); // logged-in customers get user_id attached; guests can still order
        $payload = get_request_body();
        [$isValid, $errors] = validate_order_payload($payload);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        try {
            $pdo->beginTransaction();

            $total = 0.0;
            $resolvedItems = [];
            $menuStmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id AND is_available = 1 FOR UPDATE');

            foreach ($payload['items'] as $line) {
                $menuStmt->execute([':id' => (int) $line['menu_item_id']]);
                $menuItem = $menuStmt->fetch();
                if (!$menuItem) throw new RuntimeException("Menu item id {$line['menu_item_id']} is unavailable or does not exist.");
                $qty = (int) $line['quantity'];
                $subtotal = $menuItem['price'] * $qty;
                $total += $subtotal;
                $resolvedItems[] = [
                    'menu_item_id' => $menuItem['id'], 'name' => $menuItem['name'],
                    'unit_price' => $menuItem['price'], 'quantity' => $qty, 'subtotal' => $subtotal,
                    'instructions' => $line['special_instructions'] ?? null,
                ];
            }

            $orderStmt = $pdo->prepare('
                INSERT INTO orders (user_id, customer_name, phone, order_type, delivery_address, table_number, status, total_amount, promo_code, discount_amount)
                VALUES (:user_id, :customer_name, :phone, :order_type, :delivery_address, :table_number, "pending", :total_amount, :promo_code, :discount_amount)
            ');
            [$appliedPromo, $discount] = resolve_promo_discount($payload['promo_code'] ?? null, $total);
            $finalTotal = round($total - $discount, 2);
            $orderStmt->execute([
                ':user_id' => $optionalUser['sub'] ?? null,
                ':customer_name' => sanitize_string($payload['customer_name']), ':phone' => sanitize_string($payload['phone']),
                ':order_type' => $payload['order_type'],
                ':delivery_address' => $payload['order_type'] === 'delivery' ? sanitize_string($payload['delivery_address']) : null,
                ':table_number' => $payload['table_number'] ?? null, ':total_amount' => $finalTotal,
                ':promo_code' => $appliedPromo, ':discount_amount' => $discount,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('
                INSERT INTO order_items (order_id, menu_item_id, item_name_snapshot, unit_price, quantity, subtotal, special_instructions)
                VALUES (:order_id, :menu_item_id, :name, :unit_price, :quantity, :subtotal, :instructions)
            ');
            foreach ($resolvedItems as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId, ':menu_item_id' => $item['menu_item_id'], ':name' => $item['name'],
                    ':unit_price' => $item['unit_price'], ':quantity' => $item['quantity'],
                    ':subtotal' => $item['subtotal'], ':instructions' => $item['instructions'],
                ]);
            }

            $pdo->commit();

            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();
            $order['items'] = $resolvedItems;
            json_success($order, 'Order placed successfully.', 201);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            json_error($e->getMessage(), 422);
        } catch (PDOException $e) {
            $pdo->rollBack();
            json_error('Failed to place order due to a database error.', 500);
        }
    }

    if ($method === 'PATCH' && isset($segments[1])) {
        $currentUser = require_auth();
        require_role($currentUser, ['staff', 'admin']);

        $id = (int) $segments[1];
        $payload = get_request_body();
        $validStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
        if (empty($payload['status']) || !in_array($payload['status'], $validStatuses, true)) {
            json_error('A valid status is required: ' . implode(', ', $validStatuses), 422);
        }

        $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $payload['status'], ':id' => $id]);

        $stmt2 = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt2->execute([':id' => $id]);
        $order = $stmt2->fetch();
        if (!$order) json_error('Order not found.', 404);
        json_success($order, 'Order status updated.');
    }

    json_error('Method not allowed on /orders.', 405);
}

/* =========================================================
   MY ORDERS (authenticated customer's own order history)
   ========================================================= */

if ($path === '/my/orders' && $method === 'GET') {
    $currentUser = require_auth();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute([':uid' => $currentUser['sub']]);
    json_success($stmt->fetchAll());
}

/* =========================================================
   RESERVATIONS
   ========================================================= */

if (($segments[0] ?? '') === 'reservations') {

    if ($method === 'POST') {
        $optionalUser = get_optional_user();
        $payload = get_request_body();
        [$isValid, $errors] = validate_reservation_payload($payload);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        $partySize = (int) $payload['party_size'];
        if (str_contains((string)$payload['party_size'], '6+')) $partySize = 6;
        $reservationTime = normalize_reservation_time((string)$payload['reservation_time']);

        // Prevent double-booking: cap total guests seated per date+time slot.
        $capacity = (int) env('RESTAURANT_SEATING_CAPACITY', '40');

        try {
            $pdo->beginTransaction();

            $bookedStmt = $pdo->prepare("
                SELECT COALESCE(SUM(party_size), 0) AS booked
                FROM reservations
                WHERE reservation_date = :date
                  AND reservation_time = :time
                  AND status IN ('pending', 'confirmed')
                FOR UPDATE
            ");
            $bookedStmt->execute([':date' => $payload['reservation_date'], ':time' => $reservationTime]);
            $booked = (int) $bookedStmt->fetchColumn();

            if ($booked + $partySize > $capacity) {
                $pdo->rollBack();
                json_error('That time slot is fully booked. Please choose a different time.', 409);
            }

            $stmt = $pdo->prepare('
                INSERT INTO reservations (user_id, full_name, phone, party_size, reservation_date, reservation_time, notes)
                VALUES (:user_id, :full_name, :phone, :party_size, :reservation_date, :reservation_time, :notes)
            ');
            $stmt->execute([
                ':user_id' => $optionalUser['sub'] ?? null,
                ':full_name' => sanitize_string($payload['full_name']), ':phone' => sanitize_string($payload['phone']),
                ':party_size' => $partySize, ':reservation_date' => $payload['reservation_date'],
                ':reservation_time' => $reservationTime,
                ':notes' => sanitize_string($payload['notes'] ?? ''),
            ]);
            $reservationId = (int) $pdo->lastInsertId();

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            json_error('Failed to create reservation due to a database error.', 500);
        }

        json_success(['id' => $reservationId], 'Reservation request received.', 201);
    }

    if ($method === 'GET') {
        $currentUser = require_auth();
        require_role($currentUser, ['staff', 'admin']);
        $stmt = $pdo->query('SELECT * FROM reservations ORDER BY reservation_date, reservation_time');
        json_success($stmt->fetchAll());
    }

    if ($method === 'PATCH' && isset($segments[1])) {
        $currentUser = require_auth();
        require_role($currentUser, ['staff', 'admin']);

        $id = (int) $segments[1];
        $payload = get_request_body();
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (empty($payload['status']) || !in_array($payload['status'], $validStatuses, true)) {
            json_error('A valid status is required: ' . implode(', ', $validStatuses), 422);
        }

        $stmt = $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $payload['status'], ':id' => $id]);
        if ($stmt->rowCount() === 0) json_error('Reservation not found.', 404);
        json_success(['id' => $id, 'status' => $payload['status']], 'Reservation updated.');
    }

    json_error('Method not allowed on /reservations.', 405);
}

if ($path === '/admin/summary' && $method === 'GET') {
    $currentUser = require_auth();
    require_role($currentUser, ['staff', 'admin']);

    $today = date('Y-m-d');

    $ordersToday = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = :today");
    $ordersToday->execute([':today' => $today]);

    $revenueToday = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) FROM orders
        WHERE DATE(created_at) = :today AND status != 'cancelled'
    ");
    $revenueToday->execute([':today' => $today]);

    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','preparing')")->fetchColumn();
    $pendingReservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();

    json_success([
        'orders_today'          => (int) $ordersToday->fetchColumn(),
        'revenue_today'         => (float) $revenueToday->fetchColumn(),
        'pending_orders'        => (int) $pendingOrders,
        'pending_reservations'  => (int) $pendingReservations,
    ]);
}

/* =========================================================
   ADMIN — USER MANAGEMENT
   ========================================================= */

if ($path === '/admin/users' && $method === 'GET') {
    $currentUser = require_auth();
    require_role($currentUser, ['admin']);
    $stmt = $pdo->query('SELECT id, full_name, email, phone, role, is_active, created_at FROM users ORDER BY created_at DESC');
    json_success($stmt->fetchAll());
}

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
    if ($stmt->rowCount() === 0) json_error('User not found.', 404);
    json_success(['id' => $targetId, 'role' => $payload['role']], 'User role updated.');
}

json_error("Route {$path} not found.", 404);
