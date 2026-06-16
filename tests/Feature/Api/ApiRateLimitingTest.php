<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.config('skycenter.automation_api_token')];
    }

    public function test_api_requests_are_throttled_after_limit(): void
    {
        $endpoint = '/api/automation/parking-reservations';

        // Send 60 requests which should all be accepted (or fail validation but not throttled)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson($endpoint, [], $this->headers());
            // It should not be a 429
            $this->assertNotEquals(429, $response->status());
        }

        // The 61st request should be throttled
        $response = $this->postJson($endpoint, [], $this->headers());
        $response->assertStatus(429);
    }
}
