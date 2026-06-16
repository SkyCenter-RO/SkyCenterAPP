<?php

namespace Tests\Feature\Scheduling;

use App\Filament\Pages\OrdineaDeZi;
use App\Models\User;
use App\Models\WorkShift;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdineaDeZiDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_retrieves_active_shifts_for_selected_date(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        $bratan = User::query()->where('name', 'Bratan')->first();
        $matei = User::query()->where('name', 'Matei')->first();

        // Seed explicit shift
        WorkShift::create(['date' => '2026-06-01', 'shift_type' => 'zi', 'user_id' => $bratan->id]);
        WorkShift::create(['date' => '2026-06-01', 'shift_type' => 'noapte', 'user_id' => $matei->id]);

        Livewire::actingAs($admin)
            ->test(OrdineaDeZi::class, ['selectedDate' => '2026-06-01'])
            ->assertSee('Bratan')
            ->assertSee('Matei');
    }
}
