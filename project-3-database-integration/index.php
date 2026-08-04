<?php
/**
 * Savoria — Project 3: Database Integration
 * index.php — front controller / router (MySQL + PDO)
 *
 * Routes:
 *   GET    /categories                 List categories
 *   GET    /menu-items                 List menu items (?category_id=, ?available_only=true)
 *   GET    /menu-items/{id}            Get a single menu item
 *   POST   /menu-items                 Create a menu item
 *   PUT    /menu-items/{id}            Update a menu item
 *   DELETE /menu-items/{id}            Delete a menu item
 *   GET    /orders                     List orders
 *   GET    /orders/{id}                Get an order with its line items
 *   POST   /orders                     Create an order (transactional)
 *   PATCH  /orders/{id}                Update order status
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/validate.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$path = get_route_path();
$segments = array_values(array_filter(explode('/', $path)));

if ($path === '/' && $method === 'GET') {
    json_success(['name' => 'Savoria Database Integration API', 'version' => '1.0.0'], 'API is running.');
}

// ---------------------------------------------------------
// /categories
// ---------------------------------------------------------
if (($segments[0] ?? '') === 'categories' && $method === 'GET') {
    $stmt = $pdo->query('SELECT id, name, description, sort_order FROM categories ORDER BY sort_order ASC');
    json_success($stmt->fetchAll());
}

// ---------------------------------------------------------
// /menu-items
// ---------------------------------------------------------
if (($segments[0] ?? '') === 'menu-items') {

    // GET /menu-items/{id}
    if ($method === 'GET' && isset($segments[1])) {
        $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt->execute([':id' => (int) $segments[1]]);
        $item = $stmt->fetch();
        if (!$item) json_error('Menu item not found.', 404);
        json_success($item);
    }

    // GET /menu-items
    if ($method === 'GET') {
        $sql = 'SELECT m.*, c.name AS category_name FROM menu_items m
                JOIN categories c ON c.id = m.category_id WHERE 1=1';
        $params = [];

        if (!empty($_GET['category_id'])) {
            $sql .= ' AND m.category_id = :category_id';
            $params[':category_id'] = (int) $_GET['category_id'];
        }
        if (($_GET['available_only'] ?? '') === 'true') {
            $sql .= ' AND m.is_available = 1';
        }
        $sql .= ' ORDER BY m.category_id, m.name';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_success($stmt->fetchAll());
    }

    // POST /menu-items
    if ($method === 'POST') {
        $payload = get_request_body();
        [$isValid, $errors] = validate_menu_item_payload($payload);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        $stmt = $pdo->prepare('
            INSERT INTO menu_items (category_id, name, description, price, image_url, spice_level, prep_time_minutes, is_available)
            VALUES (:category_id, :name, :description, :price, :image_url, :spice_level, :prep_time_minutes, :is_available)
        ');
        $stmt->execute([
            ':category_id'       => (int) $payload['category_id'],
            ':name'              => sanitize_string($payload['name']),
            ':description'       => sanitize_string($payload['description'] ?? ''),
            ':price'             => (float) $payload['price'],
            ':image_url'         => $payload['image_url'] ?? null,
            ':spice_level'       => (int) ($payload['spice_level'] ?? 0),
            ':prep_time_minutes' => (int) ($payload['prep_time_minutes'] ?? 15),
            ':is_available'      => isset($payload['is_available']) ? (bool) $payload['is_available'] : true,
        ]);

        $newId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt->execute([':id' => $newId]);
        json_success($stmt->fetch(), 'Menu item created.', 201);
    }

    // PUT /menu-items/{id}
    if ($method === 'PUT' && isset($segments[1])) {
        $id = (int) $segments[1];
        $payload = get_request_body();
        [$isValid, $errors] = validate_menu_item_payload($payload, true);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        $fields = [];
        $params = [':id' => $id];
        $allowed = ['category_id', 'name', 'description', 'price', 'image_url', 'spice_level', 'prep_time_minutes', 'is_available'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $payload[$field];
            }
        }
        if (empty($fields)) json_error('No updatable fields provided.', 422);

        $sql = 'UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM menu_items WHERE id = :id');
            $check->execute([':id' => $id]);
            if (!$check->fetch()) json_error('Menu item not found.', 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        json_success($stmt->fetch(), 'Menu item updated.');
    }

    // DELETE /menu-items/{id}
    if ($method === 'DELETE' && isset($segments[1])) {
        $id = (int) $segments[1];
        $stmt = $pdo->prepare('DELETE FROM menu_items WHERE id = :id');
        try {
            $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Likely a foreign key violation because the item appears in existing orders
            json_error('Cannot delete this item — it is referenced by existing orders.', 409);
        }
        if ($stmt->rowCount() === 0) json_error('Menu item not found.', 404);
        json_success(null, 'Menu item deleted.');
    }

    json_error('Method not allowed on /menu-items.', 405);
}

// ---------------------------------------------------------
// /orders
// ---------------------------------------------------------
if (($segments[0] ?? '') === 'orders') {

    // GET /orders/{id} — with line items
    if ($method === 'GET' && isset($segments[1])) {
        $id = (int) $segments[1];
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();
        if (!$order) json_error('Order not found.', 404);

        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $itemsStmt->execute([':id' => $id]);
        $order['items'] = $itemsStmt->fetchAll();

        json_success($order);
    }

    // GET /orders
    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC');
        json_success($stmt->fetchAll());
    }

    // POST /orders — transactional insert across orders + order_items
    if ($method === 'POST') {
        $payload = get_request_body();
        [$isValid, $errors] = validate_order_payload($payload);
        if (!$isValid) json_error('Validation failed.', 422, $errors);

        try {
            $pdo->beginTransaction();

            // Resolve items and compute total from the DB (never trust client prices)
            $total = 0.0;
            $resolvedItems = [];
            $menuStmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = :id AND is_available = 1 FOR UPDATE');

            foreach ($payload['items'] as $line) {
                $menuStmt->execute([':id' => (int) $line['menu_item_id']]);
                $menuItem = $menuStmt->fetch();
                if (!$menuItem) {
                    throw new RuntimeException("Menu item id {$line['menu_item_id']} is unavailable or does not exist.");
                }
                $qty = (int) $line['quantity'];
                $subtotal = $menuItem['price'] * $qty;
                $total += $subtotal;
                $resolvedItems[] = [
                    'menu_item_id' => $menuItem['id'],
                    'name'         => $menuItem['name'],
                    'unit_price'   => $menuItem['price'],
                    'quantity'     => $qty,
                    'subtotal'     => $subtotal,
                    'instructions' => $line['special_instructions'] ?? null,
                ];
            }

            $orderStmt = $pdo->prepare('
                INSERT INTO orders (customer_name, phone, order_type, delivery_address, table_number, status, total_amount)
                VALUES (:customer_name, :phone, :order_type, :delivery_address, :table_number, :status, :total_amount)
            ');
            $orderStmt->execute([
                ':customer_name'    => sanitize_string($payload['customer_name']),
                ':phone'            => sanitize_string($payload['phone']),
                ':order_type'       => $payload['order_type'],
                ':delivery_address' => $payload['order_type'] === 'delivery' ? sanitize_string($payload['delivery_address']) : null,
                ':table_number'     => $payload['table_number'] ?? null,
                ':status'           => 'pending',
                ':total_amount'     => $total,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('
                INSERT INTO order_items (order_id, menu_item_id, item_name_snapshot, unit_price, quantity, subtotal, special_instructions)
                VALUES (:order_id, :menu_item_id, :name, :unit_price, :quantity, :subtotal, :instructions)
            ');
            foreach ($resolvedItems as $item) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':menu_item_id' => $item['menu_item_id'],
                    ':name'         => $item['name'],
                    ':unit_price'   => $item['unit_price'],
                    ':quantity'     => $item['quantity'],
                    ':subtotal'     => $item['subtotal'],
                    ':instructions' => $item['instructions'],
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

    // PATCH /orders/{id}
    if ($method === 'PATCH' && isset($segments[1])) {
        $id = (int) $segments[1];
        $payload = get_request_body();
        $validStatuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

        if (empty($payload['status']) || !in_array($payload['status'], $validStatuses, true)) {
            json_error('A valid status is required: ' . implode(', ', $validStatuses), 422);
        }

        $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $payload['status'], ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM orders WHERE id = :id');
            $check->execute([':id' => $id]);
            if (!$check->fetch()) json_error('Order not found.', 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        json_success($stmt->fetch(), 'Order status updated.');
    }

    json_error('Method not allowed on /orders.', 405);
}

json_error("Route {$path} not found.", 404);
