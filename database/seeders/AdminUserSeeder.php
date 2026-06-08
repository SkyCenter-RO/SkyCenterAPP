<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew([
            'email' => 'admin@skycenter.local',
        ]);

        if (! $admin->exists) {
            $admin->password = Hash::make('schimba-parola');
        }

        $admin->name = 'Administrator';
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->save();
    }
}
