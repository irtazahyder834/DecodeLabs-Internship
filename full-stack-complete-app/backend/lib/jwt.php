<?php
/**
 * Savoria — Project 4: Authentication & Authorization
 * lib/jwt.php — minimal, dependency-free JWT (HS256) implementation
 *
 * Implements just enough of RFC 7519 for this project's needs:
 * encode, decode + signature verification, and expiry checking.
 * For production use, prefer a maintained library such as firebase/php-jwt.
 */

declare(strict_types=1);

function jwt_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $data): string
{
    $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
    return base64_decode(strtr($padded, '-_', '+/'));
}

/**
 * Encode a payload into a signed JWT.
 */
function jwt_encode(array $payload, string $secret, int $expirySeconds): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];

    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + $expirySeconds;

    $segments = [
        jwt_base64url_encode(json_encode($header)),
        jwt_base64url_encode(json_encode($payload)),
    ];

    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = jwt_base64url_encode($signature);

    return implode('.', $segments);
}

/**
 * Decode and verify a JWT. Returns the payload array, or null if
 * the token is malformed, has an invalid signature, or is expired.
 */
function jwt_decode(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $signingInput = "$headerB64.$payloadB64";
    $expectedSignature = hash_hmac('sha256', $signingInput, $secret, true);
    $actualSignature = jwt_base64url_decode($signatureB64);

    if (!hash_equals($expectedSignature, $actualSignature)) {
        return null; // signature mismatch — token was tampered with or signed by a different secret
    }

    $payload = json_decode(jwt_base64url_decode($payloadB64), true);
    if (!is_array($payload)) {
        return null;
    }

    if (isset($payload['exp']) && time() >= $payload['exp']) {
        return null; // expired
    }

    return $payload;
}
