<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;

class AdminInvitationAccepted
{
    public function __construct(
        public readonly AdminInvitation $invitation,
        public readonly Admin $admin,
    ) {}
}
