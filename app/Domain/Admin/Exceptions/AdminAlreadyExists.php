<?php

namespace App\Domain\Admin\Exceptions;

use DomainException;

class AdminAlreadyExists extends DomainException
{
    public function __construct()
    {
        parent::__construct('An administrator account already uses this e-mail address.');
    }
}
