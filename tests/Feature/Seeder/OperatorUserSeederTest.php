<?php

namespace Tests\Feature\Seeder;

use App\Models\User;
use Database\Seeders\OperatorUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_it_uses_bootstrap_operator_password(): void
    {
        putenv('BOOTSTRAP_OPERATOR_PASSWORD=operator-custom-pass');

        $this->seed(OperatorUserSeeder::class);

        $user = User::query()->where('email', 'bratan@skycenter.local')->firstOrFail();
        $this->assertTrue(Hash::check('operator-custom-pass', $user->password));

        putenv('BOOTSTRAP_OPERATOR_PASSWORD');
    }

    public function test_production_rejects_placeholder_operator_password(): void
    {
        config(['app.env' => 'production']);
        putenv('BOOTSTRAP_OPERATOR_PASSWORD=parola-operator');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BOOTSTRAP_OPERATOR_PASSWORD');

        try {
            $this->seed(OperatorUserSeeder::class);
        } finally {
            putenv('BOOTSTRAP_OPERATOR_PASSWORD');
        }
    }
}
