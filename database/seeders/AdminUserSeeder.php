<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@skycenter.local'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('schimba-parola'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
