<?php

namespace Tests\Feature\Schema;

use App\Models\ParkingReservation;
use App\Models\LodgingReservation;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Salary;
use App\Models\OutboundMessage;
use App\Models\AutomationWebhookLog;
use App\Enums\ParkingReservationStatus;
use App\Enums\LodgingReservationStatus;
use App\Enums\RentContractStatus;
use App\Enums\RentVehicleStatus;
use App\Enums\SalaryStatus;
use App\Enums\OutboundMessageStatus;
use App\Enums\AutomationWebhookLogStatus;
use Tests\TestCase;

class EnumCastsTest extends TestCase
{
    public function test_models_cast_status_to_enums(): void
    {
        $this->assertSame(ParkingReservationStatus::class, (new ParkingReservation)->getCasts()['status'] ?? null);
        $this->assertSame(LodgingReservationStatus::class, (new LodgingReservation)->getCasts()['status'] ?? null);
        $this->assertSame(RentContractStatus::class, (new RentContract)->getCasts()['status'] ?? null);
        $this->assertSame(RentVehicleStatus::class, (new RentVehicle)->getCasts()['status'] ?? null);
        $this->assertSame(SalaryStatus::class, (new Salary)->getCasts()['status'] ?? null);
        $this->assertSame(OutboundMessageStatus::class, (new OutboundMessage)->getCasts()['status'] ?? null);
        $this->assertSame(AutomationWebhookLogStatus::class, (new AutomationWebhookLog)->getCasts()['status'] ?? null);
    }
}
