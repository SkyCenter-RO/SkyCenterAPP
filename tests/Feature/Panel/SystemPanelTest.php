<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SystemPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    #[DataProvider('crudSlugs')]
    public function test_index_and_create_render(string $slug): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/{$slug}")->assertSuccessful();
        $this->actingAs($admin)->get("/admin/{$slug}/create")->assertSuccessful();
    }

    public function test_automation_journal_index_renders(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/sistem-automatizari')->assertSuccessful();
    }

    public static function crudSlugs(): array
    {
        return [
            ['sistem-plati'],
            ['sistem-sabloane'],
            ['sistem-mesaje-trimise'],
            ['sistem-utilizatori'],
        ];
    }
}
