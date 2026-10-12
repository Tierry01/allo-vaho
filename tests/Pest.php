<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests written with Pest run on top of the application TestCase,
| which refuses to boot outside the dedicated PostgreSQL test database.
|
*/

pest()->extend(TestCase::class)->in('Feature');
