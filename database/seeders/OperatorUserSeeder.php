<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorUserSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            'Bratan' => 'bratan@skycenter.local',
            'Bogdan' => 'bogdan@skycenter.local',
            'Matei' => 'matei@skycenter.local',
            'Catalin' => 'catalin@skycenter.local',
        ];

        foreach ($operators as $name => $email) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->role = User::ROLE_OPERATOR;
            $user->is_active = true;
            if (! $user->exists) {
                $password = env('BOOTSTRAP_OPERATOR_PASSWORD', 'parola-operator');

                if (config('app.env') === 'production' && $password === 'parola-operator') {
                    throw new \RuntimeException(
                        'BOOTSTRAP_OPERATOR_PASSWORD must be set to a non-placeholder value in production.',
                    );
                }

                $user->password = Hash::make($password);
            }
            $user->save();
        }
    }
}
