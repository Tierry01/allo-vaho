<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;

/**
 * A wrong verification code was entered at sign-in. Carries no code.
 */
class TwoFactorChallengeFailed
{
    public function __construct(public readonly Admin $admin) {}
}
