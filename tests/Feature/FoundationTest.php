<?php

use Illuminate\Support\Facades\DB;

it('runs on the dedicated PostgreSQL test database', function () {
    expect(app()->environment())->toBe('testing')
        ->and(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('select current_database() as name')->name)->toBe('allo_vaho_test');
});

it('talks to PostgreSQL 16 in UTC', function () {
    expect((int) DB::selectOne('show server_version_num')->server_version_num)
        ->toBeGreaterThanOrEqual(160000)
        ->toBeLessThan(170000)
        ->and(DB::selectOne('show timezone')->TimeZone)->toBe('UTC')
        ->and(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Africa/Douala');
});

it('can run PostGIS spatial operations', function () {
    $douala = 'ST_SetSRID(ST_MakePoint(9.7679, 4.0511), 4326)::geography';
    $yaounde = 'ST_SetSRID(ST_MakePoint(11.5021, 3.8480), 4326)::geography';

    $row = DB::selectOne("select ST_Distance({$douala}, {$yaounde}) as meters, ST_DWithin({$douala}, {$yaounde}, 1000) as near");

    expect((float) $row->meters)->toBeGreaterThan(190_000)->toBeLessThan(200_000)
        ->and($row->near)->toBeFalse();
});
