<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_reports_the_api_and_database_are_up(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'database' => 'up'])
            ->assertJsonStructure(['status', 'database', 'time']);
    }

    public function test_unknown_route_returns_json_not_html(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
