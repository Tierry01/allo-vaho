<?php

it('reports that the API is reachable', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJsonStructure(['status', 'version', 'timestamp'])
        ->assertJson(['status' => 'ok', 'version' => 'v1']);
});

it('is rate limited', function () {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-RateLimit-Limit', 60);
});

it('renders unknown API routes as JSON', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});
