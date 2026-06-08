<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LodgingPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    #[DataProvider('lodgingSlugs')]
    public function test_index_and_create_pages_render(string $slug): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/{$slug}")->assertSuccessful();
        $this->actingAs($admin)->get("/admin/{$slug}/create")->assertSuccessful();
    }

    public static function lodgingSlugs(): array
    {
        return [
            ['cazare-proprietati'],
            ['cazare-camere'],
            ['cazare-rezervari'],
        ];
    }
}
