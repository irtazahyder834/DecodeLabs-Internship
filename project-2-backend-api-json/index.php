<?php
/**
 * Savoria — Project 2: Backend API (JSON storage)
 * index.php — front controller / router
 *
 * A dependency-free REST API demonstrating clean routing, request
 * validation, and file-based persistence — no database required.
 *
 * Routes:
 *   GET    /                          API info
 *   GET    /menu                      List menu items (?category=, ?available_only=true)
 *   GET    /menu/{id}                 Get a single menu item
 *   GET    /orders                    List all orders
 *   GET    /orders/{id}               Get a single order
 *   POST   /orders                    Create a new order
 *   PATCH  /orders/{id}                Update an order's status
 *   DELETE /orders/{id}                Cancel/delete an order
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/validate.php';

// ---------- CORS (open for demo purposes) ----------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const MENU_FILE   = __DIR__ . '/data/menu.json';
const ORDERS_FILE = __DIR__ . '/data/orders.json';

$method = $_SERVER['REQUEST_METHOD'];
$path   = get_route_path(); // e.g. "/menu", "/orders/3"
$segments = array_values(array_filter(explode('/', $path)));

// ---------- Route: / ----------
if ($path === '/' && $method === 'GET') {
    json_success([
        'name'    => 'Savoria Backend API (JSON storage)',
        'version' => '1.0.0',
        'routes'  => [
            'GET /menu', 'GET /menu/{id}',
            'GET /orders', 'GET /orders/{id}', 'POST /orders',
            'PATCH /orders/{id}', 'DELETE /orders/{id}',
        ],
    ], 'Savoria API is running.');
}

// ---------- Route: /menu ----------
if (($segments[0] ?? '') === 'menu') {
    if ($method !== 'GET') {
        json_error('Method not allowed on /menu.', 405);
    }

    $menu = read_json_file(MENU_FILE);

    // GET /menu/{id}
    if (isset($segments[1])) {
        $id = (int) $segments[1];
        $item = current(array_filter($menu, fn($m) => (int)$m['id'] === $id));
        if (!$item) {
            json_error("Menu item with id {$id} not found.", 404);
        }
        json_success($item);
    }

    // GET /menu (with optional filters)
    $category = $_GET['category'] ?? null;
    $availableOnly = ($_GET['available_only'] ?? '') === 'true';

    $filtered = array_values(array_filter($menu, function ($item) use ($category, $availableOnly) {
        if ($category && $item['category'] !== $category) return false;
        if ($availableOnly && !$item['is_available']) return false;
        return true;
    }));

    json_success($filtered, 'Menu retrieved.', 200);
}

// ---------- Route: /orders ----------
if (($segments[0] ?? '') === 'orders') {
    $orders = read_json_file(ORDERS_FILE);
    $menu   = read_json_file(MENU_FILE);

    // GET /orders/{id}
    if ($method === 'GET' && isset($segments[1])) {
        $id = (int) $segments[1];
        $order = current(array_filter($orders, fn($o) => (int)$o['id'] === $id));
        if (!$order) {
            json_error("Order with id {$id} not found.", 404);
        }
        json_success($order);
    }

    // GET /orders
    if ($method === 'GET') {
        // newest first
        usort($orders, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        json_success($orders, 'Orders retrieved.');
    }

    // POST /orders — create a new order
    if ($method === 'POST') {
        $payload = get_request_body();
        [$isValid, $errors] = validate_order_payload($payload);
        if (!$isValid) {
            json_error('Validation failed.', 422, $errors);
        }

        // Resolve items against the menu, compute pricing server-side
        // (never trust client-submitted prices).
        $orderItems = [];
        $total = 0.0;
        foreach ($payload['items'] as $line) {
            $menuItem = current(array_filter($menu, fn($m) => (int)$m['id'] === (int)$line['menu_item_id']));
            if (!$menuItem) {
                json_error("Menu item id {$line['menu_item_id']} does not exist.", 422);
            }
            if (!$menuItem['is_available']) {
                json_error("'{$menuItem['name']}' is currently unavailable.", 422);
            }
            $qty = (int) $line['quantity'];
            $subtotal = $menuItem['price'] * $qty;
            $total += $subtotal;
            $orderItems[] = [
                'menu_item_id' => $menuItem['id'],
                'name'         => $menuItem['name'],
                'unit_price'   => $menuItem['price'],
                'quantity'     => $qty,
                'subtotal'     => $subtotal,
            ];
        }

        $newOrder = [
            'id'               => next_id($orders),
            'customer_name'    => sanitize_string($payload['customer_name']),
            'phone'            => sanitize_string($payload['phone']),
            'order_type'       => $payload['order_type'],
            'delivery_address' => $payload['order_type'] === 'delivery'
                ? sanitize_string($payload['delivery_address']) : null,
            'items'            => $orderItems,
            'total_amount'     => $total,
            'status'           => 'pending',
            'created_at'       => date('c'),
        ];

        $orders[] = $newOrder;
        if (!write_json_file(ORDERS_FILE, $orders)) {
            json_error('Failed to save order. Check file permissions on data/orders.json.', 500);
        }

        json_success($newOrder, 'Order placed successfully.', 201);
    }

    // PATCH /orders/{id} — update status
    if ($method === 'PATCH' && isset($segments[1])) {
        $id = (int) $segments[1];
        $payload = get_request_body();
        $validStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

        if (empty($payload['status']) || !in_array($payload['status'], $validStatuses, true)) {
            json_error('A valid status is required: ' . implode(', ', $validStatuses), 422);
        }

        $found = false;
        foreach ($orders as &$order) {
            if ((int)$order['id'] === $id) {
                $order['status'] = $payload['status'];
                $order['updated_at'] = date('c');
                $found = true;
                $updated = $order;
                break;
            }
        }
        unset($order);

        if (!$found) {
            json_error("Order with id {$id} not found.", 404);
        }

        write_json_file(ORDERS_FILE, $orders);
        json_success($updated, 'Order status updated.');
    }

    // DELETE /orders/{id}
    if ($method === 'DELETE' && isset($segments[1])) {
        $id = (int) $segments[1];
        $before = count($orders);
        $orders = array_values(array_filter($orders, fn($o) => (int)$o['id'] !== $id));

        if (count($orders) === $before) {
            json_error("Order with id {$id} not found.", 404);
        }

        write_json_file(ORDERS_FILE, $orders);
        json_success(null, 'Order deleted.');
    }

    json_error('Method not allowed on /orders.', 405);
}

// ---------- Fallback ----------
json_error("Route {$path} not found.", 404);
