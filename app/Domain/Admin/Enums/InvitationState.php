<?php

namespace App\Domain\Admin\Enums;

/**
 * What an invitation link resolves to when it is opened.
 */
enum InvitationState: string
{
    case Usable = 'usable';
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Invalid = 'invalid';
}
