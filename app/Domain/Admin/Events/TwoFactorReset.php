<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;

/**
 * A Super Admin reset another administrator's two-factor authentication.
 */
class TwoFactorReset
{
    public function __construct(
        public readonly Admin $admin,
        public readonly Admin $resetBy,
        public readonly string $reason,
    ) {}
}
