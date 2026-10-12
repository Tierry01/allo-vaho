<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotent: does nothing when PostGIS is already enabled, so the role
     * running migrations only needs CREATE EXTENSION rights on a new database.
     */
    public function up(): void
    {
        if (DB::selectOne("select 1 from pg_extension where extname = 'postgis'")) {
            return;
        }

        // PostGIS is not a trusted extension: this requires a superuser. On a
        // server where the application role is not one, have an administrator
        // run this statement once on the database before migrating.
        DB::statement('create extension if not exists postgis');
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally left empty: dropping PostGIS would destroy every spatial
     * column of the database.
     */
    public function down(): void
    {
        //
    }
};
