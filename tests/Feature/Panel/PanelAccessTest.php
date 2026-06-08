<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function panel()
    {
        return Filament::getPanel('admin');
    }

    public function test_active_admin_can_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_active_operator_can_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_role_helpers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($operator->isAdmin());
        $this->assertTrue($operator->isOperator());
        $this->assertTrue($admin->hasAnyRole(['admin', 'operator']));
    }

    public function test_login_screen_is_reachable(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_active_admin_can_open_panel_home(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_active_operator_can_open_panel_home(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OPERATOR, 'is_active' => true]);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_inactive_user_is_denied_panel_home(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
