<?php

namespace Tests\Feature\Panel;

use App\Models\BudgetCategory;
use App\Models\BudgetRawMessage;
use App\Models\BudgetTransaction;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_category_and_salary_relations(): void
    {
        $category = BudgetCategory::create([
            'service' => 'hotel', 'name' => 'apă', 'kind' => 'expense', 'frequency' => 'monthly',
        ]);
        $raw = BudgetRawMessage::create([
            'chat_id' => '123', 'message_id' => '456', 'text' => 'apa 100 lei',
        ]);
        $transaction = BudgetTransaction::create([
            'type' => 'expense',
            'category_id' => $category->id,
            'raw_message_id' => $raw->id,
            'amount' => 100.00,
            'occurred_on' => '2026-06-08',
        ]);
        $user = User::factory()->create();
        $salary = Salary::create([
            'user_id' => $user->id,
            'amount' => 4000.00,
            'period_month' => '2026-06-01',
            'status' => 'pending',
        ]);

        $this->assertSame('apă', $transaction->category->name);
        $this->assertSame($raw->id, $transaction->rawMessage->id);
        $this->assertSame('expense', $transaction->type);
        $this->assertSame($user->id, $salary->user->id);
    }
}
