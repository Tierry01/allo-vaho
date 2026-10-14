<?php

namespace App\Domain\Admin\Exceptions;

use DomainException;

/**
 * The last active Super Admin cannot be suspended or deleted: nobody could
 * administer the back-office any more.
 */
class LastSuperAdminProtected extends DomainException
{
    public function __construct()
    {
        parent::__construct('The last active Super Admin cannot be suspended or deleted.');
    }
}
