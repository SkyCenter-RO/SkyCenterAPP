<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParkingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $lot1 = DB::table('parking_lots')->insertGetId([
            'name' => 'Parcarea 1', 'total_spaces' => 54,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('parking_lots')->insert([
            'name' => 'Parcarea 2', 'total_spaces' => 30,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zones = ['A' => 11, 'B' => 8, 'C' => 14, 'D' => 12, 'E' => 9];
        foreach ($zones as $code => $capacity) {
            DB::table('parking_zones')->insert([
                'lot_id' => $lot1, 'code' => $code, 'capacity' => $capacity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
