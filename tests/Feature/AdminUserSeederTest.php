<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_admin_on_a_fresh_database(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@skycenter.local')->firstOrFail();

        $this->assertSame('Administrator', $admin->name);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('schimba-parola', $admin->password));
    }

    public function test_reseeding_restores_admin_fields_without_resetting_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@skycenter.local',
            'name' => 'Cont vechi',
            'password' => 'parola-schimbata',
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin->refresh();

        $this->assertSame('Administrator', $admin->name);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('parola-schimbata', $admin->password));
        $this->assertFalse(Hash::check('schimba-parola', $admin->password));
    }
}
