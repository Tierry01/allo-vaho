<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;

/**
 * An administrator completed the authenticator enrolment. Carries no secret.
 */
class TwoFactorEnrolled
{
    public function __construct(public readonly Admin $admin) {}
}
