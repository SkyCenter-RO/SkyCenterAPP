<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LodgingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $properties = ['Sky Center' => 7, 'Serafim' => 5];
        foreach ($properties as $name => $roomCount) {
            $propertyId = DB::table('lodging_properties')->insertGetId([
                'name' => $name, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            for ($i = 1; $i <= $roomCount; $i++) {
                DB::table('rooms')->insert([
                    'property_id' => $propertyId, 'name' => "Camera $i", 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
