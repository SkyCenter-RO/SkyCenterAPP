<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_tables_exist(): void
    {
        foreach (['budget_categories', 'budget_raw_messages', 'budget_transactions', 'salaries'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing $table");
        }
        $this->assertTrue(Schema::hasColumns('budget_categories', ['service', 'name', 'kind', 'frequency']));
        $this->assertTrue(Schema::hasColumns('budget_transactions', ['type', 'amount', 'currency', 'occurred_on']));
    }

    public function test_category_frequency_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('budget_categories')->insert([
            'service' => 'hotel', 'name' => 'apă', 'kind' => 'expense', 'frequency' => 'hourly',
        ]);
    }

    public function test_transaction_type_check_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('budget_transactions')->insert([
            'type' => 'refund', 'amount' => 10, 'occurred_on' => now()->toDateString(),
        ]);
    }
}
