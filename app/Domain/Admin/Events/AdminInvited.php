<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;

/**
 * An invitation was issued. Carries no token: only its hash exists, in the database.
 */
class AdminInvited
{
    public function __construct(
        public readonly AdminInvitation $invitation,
        public readonly ?Admin $invitedBy,
    ) {}
}
