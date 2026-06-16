<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    #[DataProvider('adminOnlySlugs')]
    public function test_operator_is_forbidden_on_admin_only_resources(string $slug): void
    {
        $this->actingAs($this->user('operator'))->get("/admin/{$slug}")->assertForbidden();
    }

    #[DataProvider('adminOnlySlugs')]
    public function test_admin_can_access_admin_only_resources(string $slug): void
    {
        $this->actingAs($this->user('admin'))->get("/admin/{$slug}")->assertSuccessful();
    }

    public function test_operator_can_access_operational_resources(): void
    {
        $this->actingAs($this->user('operator'))->get('/admin/parcare-rezervari')->assertSuccessful();
        $this->actingAs($this->user('operator'))->get('/admin/cazare-rezervari')->assertSuccessful();
        $this->actingAs($this->user('operator'))->get('/admin/rent-masini')->assertSuccessful();
    }

    public static function adminOnlySlugs(): array
    {
        return [
            ['buget-categorii'],
            ['buget-tranzactii'],
            ['buget-mesaje'],
            ['buget-salarii'],
            ['sistem-plati'],
            ['sistem-utilizatori'],
            ['sistem-automatizari'],
            ['sistem-sabloane'],
            ['sistem-mesaje-trimise'],
        ];
    }
}
