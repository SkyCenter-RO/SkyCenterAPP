<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['template_key' => 'parking_booked_confirm', 'service' => 'parking', 'channel' => 'whatsapp',
             'label' => 'Confirmare parcare', 'body' => 'Bună ziua! Rezervarea dvs. de parcare a fost confirmată.'],
            ['template_key' => 'parking_departed_review', 'service' => 'parking', 'channel' => 'whatsapp',
             'label' => 'Follow-up recenzie', 'body' => 'Vă mulțumim! Cum ați aflat de noi? Ne lăsați o recenzie pe Google/Facebook?'],
        ];
        foreach ($templates as $t) {
            DB::table('message_templates')->insert(array_merge($t, [
                'source' => 'manual', 'locale' => 'ro', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
