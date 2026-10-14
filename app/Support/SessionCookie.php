<?php

namespace App\Support;

/**
 * Whether the session cookie must be sent over HTTPS only. It is, in every
 * environment except local development and the test suite, unless
 * SESSION_SECURE_COOKIE says otherwise.
 */
class SessionCookie
{
    public static function secureByDefault(?string $environment): bool
    {
        return ! in_array($environment ?? 'production', ['local', 'testing'], true);
    }
}
