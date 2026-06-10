<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'template_key' => 'confirmation',
                'service' => 'parking',
                'channel' => 'whatsapp',
                'label' => 'Confirmare parcare',
                'body' => 'Bună {{name}}! Rezervarea ta de parcare e confirmată: {{check_in}} - {{check_out}}, auto {{plate}}. Te așteptăm la Sky Center!',
            ],
            [
                'template_key' => 'confirmation',
                'service' => 'lodging',
                'channel' => 'whatsapp',
                'label' => 'Confirmare cazare',
                'body' => 'Bună {{guest_name}}! Rezervarea ta la {{property}} ({{room}}) e confirmată: {{check_in}} - {{check_out}}. Te așteptăm!',
            ],
            [
                'template_key' => 'review_request',
                'service' => 'parking',
                'channel' => 'whatsapp',
                'label' => 'Cerere recenzie parcare',
                'body' => 'Bună {{name}}! Mulțumim că ai parcat la Sky Center. Ne-ar ajuta enorm o recenzie: [link recenzie]',
            ],
            [
                'template_key' => 'review_request',
                'service' => 'lodging',
                'channel' => 'whatsapp',
                'label' => 'Cerere recenzie cazare',
                'body' => 'Bună {{guest_name}}! Mulțumim că ai stat la {{property}}. Ne-ar ajuta enorm o recenzie: [link recenzie]',
            ],
        ];

        foreach ($templates as $t) {
            DB::table('message_templates')->insert(array_merge($t, [
                'source' => 'manual',
                'locale' => 'ro',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
