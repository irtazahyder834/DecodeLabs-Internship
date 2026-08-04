<?php
/**
 * Savoria — Project 2: Backend API (JSON storage)
 * lib/validate.php — lightweight request validation helpers
 */

declare(strict_types=1);

/**
 * Validate an order submission payload.
 *
 * Expected shape:
 * {
 *   "customer_name": "Ayesha Khan",
 *   "phone": "03001234567",
 *   "order_type": "delivery" | "pickup" | "dine_in",
 *   "delivery_address": "..."   (required only when order_type = delivery),
 *   "items": [ { "menu_item_id": 1, "quantity": 2 }, ... ]
 * }
 *
 * @return array{0: bool, 1: array<string,string>} [isValid, errorsByField]
 */
function validate_order_payload(array $payload): array
{
    $errors = [];

    if (empty($payload['customer_name']) || !is_string($payload['customer_name'])) {
        $errors['customer_name'] = 'Customer name is required.';
    }

    if (empty($payload['phone']) || !preg_match('/^\d{10,13}$/', preg_replace('/\D/', '', (string)($payload['phone'] ?? '')))) {
        $errors['phone'] = 'A valid phone number is required.';
    }

    $validTypes = ['delivery', 'pickup', 'dine_in'];
    $orderType = $payload['order_type'] ?? '';
    if (!in_array($orderType, $validTypes, true)) {
        $errors['order_type'] = 'order_type must be one of: ' . implode(', ', $validTypes) . '.';
    }

    if ($orderType === 'delivery' && empty($payload['delivery_address'])) {
        $errors['delivery_address'] = 'Delivery address is required for delivery orders.';
    }

    if (empty($payload['items']) || !is_array($payload['items'])) {
        $errors['items'] = 'At least one item is required.';
    } else {
        foreach ($payload['items'] as $index => $item) {
            if (!isset($item['menu_item_id']) || !is_numeric($item['menu_item_id'])) {
                $errors["items.$index.menu_item_id"] = 'Each item requires a valid menu_item_id.';
            }
            if (!isset($item['quantity']) || !is_numeric($item['quantity']) || (int)$item['quantity'] < 1) {
                $errors["items.$index.quantity"] = 'Each item requires a quantity of at least 1.';
            }
        }
    }

    return [empty($errors), $errors];
}

/**
 * Sanitize a string for safe storage/output (basic trim + strip tags).
 */
function sanitize_string($value): string
{
    return trim(strip_tags((string) $value));
}
