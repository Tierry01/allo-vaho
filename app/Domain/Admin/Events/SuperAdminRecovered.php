<?php

namespace App\Domain\Admin\Events;

use App\Domain\Admin\Models\Admin;

/**
 * The exceptional recovery of the last Super Admin was run from the server.
 */
class SuperAdminRecovered
{
    public function __construct(
        public readonly Admin $admin,
        public readonly string $operator,
        public readonly string $reason,
        public readonly bool $passwordResetIssued,
    ) {}
}
