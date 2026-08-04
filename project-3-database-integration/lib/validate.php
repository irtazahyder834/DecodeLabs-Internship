<?php
/**
 * Savoria — Project 3: Database Integration
 * lib/validate.php — request payload validation
 */

declare(strict_types=1);

function validate_menu_item_payload(array $payload, bool $isUpdate = false): array
{
    $errors = [];

    if (!$isUpdate || array_key_exists('name', $payload)) {
        if (empty($payload['name']) || strlen((string)$payload['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }
    }

    if (!$isUpdate || array_key_exists('category_id', $payload)) {
        if (empty($payload['category_id']) || !is_numeric($payload['category_id'])) {
            $errors['category_id'] = 'A valid category_id is required.';
        }
    }

    if (!$isUpdate || array_key_exists('price', $payload)) {
        if (!isset($payload['price']) || !is_numeric($payload['price']) || (float)$payload['price'] < 0) {
            $errors['price'] = 'Price must be a non-negative number.';
        }
    }

    if (isset($payload['spice_level']) && (!is_numeric($payload['spice_level']) || $payload['spice_level'] < 0 || $payload['spice_level'] > 3)) {
        $errors['spice_level'] = 'Spice level must be between 0 and 3.';
    }

    return [empty($errors), $errors];
}

function validate_order_payload(array $payload): array
{
    $errors = [];

    if (empty($payload['customer_name'])) {
        $errors['customer_name'] = 'Customer name is required.';
    }

    if (empty($payload['phone']) || !preg_match('/^\d{10,13}$/', preg_replace('/\D/', '', (string)($payload['phone'] ?? '')))) {
        $errors['phone'] = 'A valid phone number is required.';
    }

    $validTypes = ['dine_in', 'pickup', 'delivery'];
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
