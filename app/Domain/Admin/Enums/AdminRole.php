<?php

namespace App\Domain\Admin\Enums;

/**
 * Back-office roles defined by the technical file. Their permissions are
 * decided in Phase 11; until then only the role itself is stored.
 */
enum AdminRole: string
{
    /** Guard the roles belong to. */
    public const GUARD = 'admin';

    case SuperAdmin = 'super-admin';
    case Support = 'support';
    case Marketing = 'marketing';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Support => 'Support',
            self::Marketing => 'Marketing',
            self::Finance => 'Finance',
        };
    }
}
