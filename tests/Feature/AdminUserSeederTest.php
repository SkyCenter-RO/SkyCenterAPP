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

    public function test_it_uses_the_configured_bootstrap_password(): void
    {
        config(['skycenter.bootstrap_admin_password' => 'parola-din-config']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@skycenter.local')->firstOrFail();

        $this->assertTrue(Hash::check('parola-din-config', $admin->password));
        $this->assertFalse(Hash::check('schimba-parola', $admin->password));
    }

    public function test_production_rejects_a_missing_bootstrap_password_for_a_new_admin(): void
    {
        config([
            'app.env' => 'production',
            'skycenter.bootstrap_admin_password' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_BOOTSTRAP_PASSWORD');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_production_rejects_the_placeholder_password_for_a_new_admin(): void
    {
        config([
            'app.env' => 'production',
            'skycenter.bootstrap_admin_password' => 'schimba-parola',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_BOOTSTRAP_PASSWORD');

        $this->seed(AdminUserSeeder::class);
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
