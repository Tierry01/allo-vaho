<?php

namespace App\Domain\Admin\Enums;

enum AdminStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Suspended => 'Suspendu',
        };
    }
}
