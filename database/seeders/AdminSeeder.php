<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default administrator account.
     */
    public function run(): void
    {
        $email = trim((string) env('ADMIN_SEED_EMAIL'));
        $password = (string) env('ADMIN_SEED_PASSWORD');
        $name = trim((string) env('ADMIN_SEED_NAME', 'Administrator'));

        if ($email === '' || $password === '') {
            $this->command?->warn('AdminSeeder skipped: set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD explicitly.');
            return;
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_SEED_PASSWORD must contain at least 12 characters.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
