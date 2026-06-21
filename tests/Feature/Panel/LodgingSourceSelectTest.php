<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\LodgingReservations\Schemas\LodgingReservationForm;
use App\Models\LodgingReservation;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LodgingSourceSelectTest extends TestCase
{
    private function getField(
        string $formClass,
        string $fieldName,
        ?Model $record = null,
    ): ?Component {
        $schema = (new Schema)->record($record);
        $formClass::configure($schema);

        foreach ($schema->getComponents() as $component) {
            if ($component->getName() === $fieldName) {
                return $component;
            }
        }

        return null;
    }

    public function test_new_reservation_source_is_a_required_select_with_approved_options(): void
    {
        $field = $this->getField(LodgingReservationForm::class, 'source');

        $this->assertInstanceOf(Select::class, $field);
        $this->assertTrue($field->isRequired());
        $this->assertSame('direct', $field->getDefaultState());
        $this->assertSame([
            'gmail' => 'Email',
            'booking_com' => 'Booking.com',
            'airbnb' => 'Airbnb',
            'direct' => 'Direct',
        ], $field->getOptions());
    }

    #[DataProvider('legacySources')]
    public function test_editing_a_legacy_reservation_preserves_its_current_source(
        string $source,
        string $label,
    ): void {
        $record = new LodgingReservation(['source' => $source]);
        $field = $this->getField(LodgingReservationForm::class, 'source', $record);

        $this->assertInstanceOf(Select::class, $field);
        $this->assertSame([
            'gmail' => 'Email',
            'booking_com' => 'Booking.com',
            'airbnb' => 'Airbnb',
            'direct' => 'Direct',
            $source => $label,
        ], $field->getOptions());
    }

    public static function legacySources(): array
    {
        return [
            ['manual', 'Manual (legacy)'],
            ['booking', 'Booking (legacy)'],
        ];
    }
}
