<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\AutomationWebhookLogs\Schemas\AutomationWebhookLogForm;
use App\Filament\Resources\LodgingReservations\Schemas\LodgingReservationForm;
use App\Filament\Resources\OutboundMessages\Schemas\OutboundMessageForm;
use App\Filament\Resources\ParkingReservations\Schemas\ParkingReservationForm;
use App\Filament\Resources\RentContracts\Schemas\RentContractForm;
use App\Filament\Resources\RentMaintenanceRecords\Schemas\RentMaintenanceRecordForm;
use App\Filament\Resources\RentVehicles\Schemas\RentVehicleForm;
use App\Filament\Resources\Salaries\Schemas\SalaryForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Tests\TestCase;

class FilamentFormsStatusSelectTest extends TestCase
{
    private function getField(string $formClass, string $fieldName)
    {
        $schema = new Schema;
        $formClass::configure($schema);
        foreach ($schema->getComponents() as $component) {
            if ($component->getName() === $fieldName) {
                return $component;
            }
        }

        return null;
    }

    public function test_status_fields_are_selects_with_enum_options(): void
    {
        $fields = [
            ParkingReservationForm::class,
            LodgingReservationForm::class,
            RentContractForm::class,
            RentVehicleForm::class,
            SalaryForm::class,
            OutboundMessageForm::class,
            AutomationWebhookLogForm::class,
        ];

        foreach ($fields as $formClass) {
            $field = $this->getField($formClass, 'status');
            $this->assertNotNull($field, "Status field missing on {$formClass}");
            $this->assertInstanceOf(Select::class, $field, "Status field on {$formClass} is not a Select");
        }
    }

    public function test_rent_maintenance_vehicle_field_is_searchable_relation_select(): void
    {
        $field = $this->getField(RentMaintenanceRecordForm::class, 'rent_vehicle_id');

        $this->assertNotNull($field);
        $this->assertInstanceOf(Select::class, $field);
        $this->assertSame('vehicle', $field->getRelationshipName());
        $this->assertSame('license_plate', $field->getRelationshipTitleAttribute());
        $this->assertTrue($field->isSearchable());
        $this->assertTrue($field->isPreloaded());
    }
}
