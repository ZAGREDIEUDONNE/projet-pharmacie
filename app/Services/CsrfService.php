<?php

namespace App\Services;

/** Protection CSRF dédiée aux formulaires et actions sensibles de l'administration. */
final class CsrfService
{
    private const SESSION_KEY = 'admin_csrf_token';
    private const ISSUED_AT_SESSION_KEY = 'admin_csrf_token_issued_at';
    private const TTL_SECONDS = 7200;

    public static function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        $issuedAt = (int) ($_SESSION[self::ISSUED_AT_SESSION_KEY] ?? 0);
        if (!is_string($token) || strlen($token) !== 64 || $issuedAt <= 0 || (time() - $issuedAt) >= self::TTL_SECONDS) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
            $_SESSION[self::ISSUED_AT_SESSION_KEY] = time();
        }

        return $token;
    }

    public static function isValid(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        $issuedAt = (int) ($_SESSION[self::ISSUED_AT_SESSION_KEY] ?? 0);

        return is_string($expected)
            && is_string($token)
            && $issuedAt > 0
            && (time() - $issuedAt) < self::TTL_SECONDS
            && hash_equals($expected, $token);
    }
}
