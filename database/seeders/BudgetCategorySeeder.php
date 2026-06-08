<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $hotel = ['apă', 'lumină', 'gaz', 'electricitate', 'inventar'];
        foreach ($hotel as $name) {
            DB::table('budget_categories')->insert([
                'service' => 'hotel', 'name' => $name, 'kind' => 'expense',
                'frequency' => 'monthly', 'currency' => 'RON', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
