<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * Refuses to run when the test suite is not pointed at a dedicated
     * PostgreSQL test database, before any trait can touch the schema.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $connection !== 'pgsql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Tests must run in the testing environment on a PostgreSQL database ending in \"_test\" (got [{$connection}] \"{$database}\")."
            );
        }

        return $app;
    }
}
