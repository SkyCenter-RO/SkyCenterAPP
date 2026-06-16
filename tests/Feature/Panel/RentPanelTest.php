<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    #[DataProvider('rentSlugs')]
    public function test_index_and_create_pages_render(string $slug): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/{$slug}")->assertSuccessful();
        $this->actingAs($admin)->get("/admin/{$slug}/create")->assertSuccessful();
    }

    public static function rentSlugs(): array
    {
        return [
            ['rent-masini'],
            ['rent-clienti'],
            ['rent-contracte'],
            ['rent-mentenanta'],
        ];
    }

    public function test_rent_contract_form_has_searchable_relation_selects(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\RentContracts\Pages\CreateRentContract::class)
            ->assertFormFieldExists('rent_vehicle_id')
            ->assertFormFieldExists('rent_client_id');
    }
}
