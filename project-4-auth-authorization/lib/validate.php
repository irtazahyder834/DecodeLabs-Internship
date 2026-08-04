<?php
/**
 * Savoria — Project 4: Authentication & Authorization
 * lib/validate.php — request payload validation
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
