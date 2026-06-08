<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_suite_uses_the_dedicated_test_database(): void
    {
        $this->assertSame('skycenter_app_test', DB::connection()->getDatabaseName());
    }
}
