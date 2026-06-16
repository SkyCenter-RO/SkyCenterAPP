<?php

namespace Tests\Feature\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'service', 'parking_reservation_id', 'lodging_reservation_id',
            'rent_contract_id', 'amount', 'currency', 'method', 'paid_at',
        ]));
        $this->assertTrue(Schema::hasTable('payment_change_audits'));
    }

    public function test_payment_service_check_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);
        DB::table('payments')->insert([
            'service' => 'gym', 'amount' => 10, 'method' => 'cash',
        ]);
    }
}
