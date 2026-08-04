<?php
/**
 * Savoria — Full-Stack Complete App
 * backend/lib/validate.php — request payload validation
 */

declare(strict_types=1);

function validate_register_payload(array $payload): array
{
    $errors = [];

    if (empty($payload['full_name']) || strlen((string)$payload['full_name']) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters.';
    }
    if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    $password = (string) ($payload['password'] ?? '');
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must include at least one uppercase letter and one number.';
    }
    if (isset($payload['phone']) && !preg_match('/^\d{10,13}$/', preg_replace('/\D/', '', (string)$payload['phone']))) {
        $errors['phone'] = 'Phone number format is invalid.';
    }

    return [empty($errors), $errors];
}

function validate_login_payload(array $payload): array
{
    $errors = [];
    if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    if (empty($payload['password'])) {
        $errors['password'] = 'Password is required.';
    }
    return [empty($errors), $errors];
}

function validate_forgot_password_payload(array $payload): array
{
    $errors = [];
    if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    return [empty($errors), $errors];
}

function validate_reset_password_payload(array $payload): array
{
    $errors = [];
    if (empty($payload['token'])) {
        $errors['token'] = 'Reset token is required.';
    }
    $password = (string) ($payload['password'] ?? '');
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must include at least one uppercase letter and one number.';
    }
    return [empty($errors), $errors];
}

function validate_change_password_payload(array $payload): array
{
    $errors = [];
    if (empty($payload['current_password'])) {
        $errors['current_password'] = 'Current password is required.';
    }
    $password = (string) ($payload['new_password'] ?? '');
    if (strlen($password) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['new_password'] = 'New password must include at least one uppercase letter and one number.';
    }
    return [empty($errors), $errors];
}
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

function validate_reservation_payload(array $payload): array
{
    $errors = [];

    if (empty($payload['full_name'])) $errors['full_name'] = 'Full name is required.';
    if (empty($payload['phone']) || !preg_match('/^\d{10,13}$/', preg_replace('/\D/', '', (string)($payload['phone'] ?? '')))) {
        $errors['phone'] = 'A valid phone number is required.';
    }
    $partySizeRaw = (string) ($payload['party_size'] ?? '');
    if ($partySizeRaw === '' || (!is_numeric($partySizeRaw) && !str_contains($partySizeRaw, '6+'))) {
        $errors['party_size'] = 'Party size must be at least 1.';
    } elseif (is_numeric($partySizeRaw) && (int)$partySizeRaw < 1) {
        $errors['party_size'] = 'Party size must be at least 1.';
    }
    if (empty($payload['reservation_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['reservation_date'])) {
        $errors['reservation_date'] = 'A valid date (YYYY-MM-DD) is required.';
    }
    if (empty($payload['reservation_time']) || normalize_reservation_time((string)$payload['reservation_time']) === null) {
        $errors['reservation_time'] = 'A valid reservation time is required.';
    }

    return [empty($errors), $errors];
}
