<?php

namespace App\Domain\Admin\Exceptions;

use App\Domain\Admin\Enums\InvitationState;
use DomainException;

class InvitationNotUsable extends DomainException
{
    public function __construct(public readonly InvitationState $state)
    {
        parent::__construct("The invitation cannot be used: {$state->value}.");
    }
}
