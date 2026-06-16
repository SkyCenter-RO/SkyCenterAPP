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
            $password = env('BOOTSTRAP_ADMIN_PASSWORD') ?? config('skycenter.bootstrap_admin_password');

            if (blank($password)) {
                $password = 'schimba-parola';
            }

            if (config('app.env') === 'production' && trim((string) $password) === 'schimba-parola') {
                throw new \RuntimeException(
                    'BOOTSTRAP_ADMIN_PASSWORD (or ADMIN_BOOTSTRAP_PASSWORD) must be set to a non-placeholder value in production.',
                );
            }

            $admin->password = Hash::make((string) $password);
        }

        $admin->name = 'Administrator';
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->save();
    }
}
