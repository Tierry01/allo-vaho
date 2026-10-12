<?php

use Spectator\Spectator;

beforeEach(function () {
    config([
        'spectator.sources.local.base_path' => base_path('docs/api/openapi'),
        'spectator.path_prefix' => 'api/v1',
    ]);

    Spectator::using('v1.yaml');
});

it('serves the health endpoint as described by the OpenAPI contract', function () {
    $this->getJson('/api/v1/health')
        ->assertValidRequest()
        ->assertValidResponse(200);
});
