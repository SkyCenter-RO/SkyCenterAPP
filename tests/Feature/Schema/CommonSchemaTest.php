<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommonSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_operational_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['phone', 'role', 'is_active']));
    }

    public function test_users_role_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('users')->insert([
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'x',
            'role' => 'wizard', 'is_active' => true,
        ]);
    }
}
