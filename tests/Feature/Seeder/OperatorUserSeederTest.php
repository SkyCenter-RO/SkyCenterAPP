<?php

namespace Tests\Feature\Seeder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_operator_users(): void
    {
        $this->seed();

        $operators = ['Bratan', 'Bogdan', 'Matei', 'Catalin'];

        foreach ($operators as $name) {
            $user = User::query()->where('name', $name)->first();
            $this->assertNotNull($user);
            $this->assertEquals(User::ROLE_OPERATOR, $user->role);
            $this->assertTrue($user->is_active);
        }
    }
}
