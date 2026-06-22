<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\LodgingProperties\Pages\CreateLodgingProperty;
use App\Filament\Resources\LodgingReservations\Pages\CreateLodgingReservation;
use App\Filament\Resources\LodgingReservations\Pages\EditLodgingReservation;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Models\LodgingProperty;
use App\Models\LodgingReservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LodgingSourceRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));
    }

    public function test_real_create_pages_apply_source_defaults(): void
    {
        Livewire::test(CreateLodgingProperty::class)
            ->set('data.name', 'Runtime Default Property')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoErrors();

        $property = LodgingProperty::where('name', 'Runtime Default Property')->sole();

        $this->assertSame('manual', $property->source);

        Livewire::test(CreateRoom::class)
            ->set('data.property_id', $property->id)
            ->set('data.name', 'Runtime Default Room')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoErrors();

        $room = Room::where('name', 'Runtime Default Room')->sole();

        $this->assertSame('manual', $room->source);

        Livewire::test(CreateLodgingReservation::class)
            ->set('data.room_id', $room->id)
            ->set('data.check_in', '2026-07-01')
            ->set('data.check_out', '2026-07-02')
            ->call('create')
            ->assertHasNoErrors();

        $reservation = LodgingReservation::where('room_id', $room->id)->sole();

        $this->assertSame('direct', $reservation->source);
    }

    #[DataProvider('legacySources')]
    public function test_create_rejects_forged_legacy_source_and_edit_preserves_it(
        string $source,
        string $suffix,
        string $checkIn,
        string $checkOut,
    ): void {
        $property = LodgingProperty::create([
            'name' => "Legacy Property {$suffix}",
            'is_active' => true,
        ]);
        $room = Room::create([
            'property_id' => $property->id,
            'name' => "Legacy Room {$suffix}",
            'is_active' => true,
        ]);

        Livewire::test(CreateLodgingReservation::class)
            ->set('data.room_id', $room->id)
            ->set('data.source', $source)
            ->set('data.check_in', $checkIn)
            ->set('data.check_out', $checkOut)
            ->call('create')
            ->assertHasErrors(['data.source']);

        $this->assertDatabaseCount('lodging_reservations', 0);

        $reservation = LodgingReservation::create([
            'room_id' => $room->id,
            'source' => $source,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);

        Livewire::test(EditLodgingReservation::class, [
            'record' => $reservation->getRouteKey(),
        ])
            ->assertSet('data.source', $source)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($source, $reservation->refresh()->source);
    }

    public static function legacySources(): array
    {
        return [
            'manual' => ['manual', 'Manual', '2026-07-03', '2026-07-04'],
            'booking' => ['booking', 'Booking', '2026-07-05', '2026-07-06'],
        ];
    }
}
