<?php

namespace App\Domain\Admin\Support;

use SensitiveParameter;

/**
 * Invitation tokens: 256 random bits sent to the invitee, of which only the
 * SHA-256 hash is ever stored.
 */
class InvitationToken
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }
}
