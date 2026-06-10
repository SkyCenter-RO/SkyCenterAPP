<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('budget_categories')->truncate();

        $categories = [
            // General expenses (shown in expense wizard)
            ['service' => 'general', 'name' => 'Kaufland',        'emoji' => '🛒'],
            ['service' => 'general', 'name' => 'Curățenie',       'emoji' => '🧹'],
            ['service' => 'general', 'name' => 'Spălat mașini',   'emoji' => '🚗'],
            ['service' => 'general', 'name' => 'Instalatori',     'emoji' => '🔧'],
            ['service' => 'general', 'name' => 'Grădinar',        'emoji' => '🌿'],
            ['service' => 'general', 'name' => 'Contabil',        'emoji' => '📊'],
            ['service' => 'general', 'name' => 'Combustibil',     'emoji' => '⛽'],
            ['service' => 'general', 'name' => 'Piese auto',      'emoji' => '🔩'],
            ['service' => 'general', 'name' => 'Rovinietă',       'emoji' => '📋'],
            ['service' => 'general', 'name' => 'Salarii',         'emoji' => '👤'],
            // Hotel-specific expenses
            ['service' => 'hotel',   'name' => 'Apă',             'emoji' => '💧'],
            ['service' => 'hotel',   'name' => 'Lumină',          'emoji' => '💡'],
            ['service' => 'hotel',   'name' => 'Gaz',             'emoji' => '🔥'],
            ['service' => 'hotel',   'name' => 'Inventar',        'emoji' => '📦'],
        ];

        foreach ($categories as $cat) {
            DB::table('budget_categories')->insert([
                'service'    => $cat['service'],
                'name'       => $cat['name'],
                'kind'       => 'expense',
                'frequency'  => 'once',
                'currency'   => 'RON',
                'is_active'  => true,
                'metadata'   => json_encode(['emoji' => $cat['emoji']]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
