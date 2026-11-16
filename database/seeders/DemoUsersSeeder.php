<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The demo accounts behind the one-click login buttons (DEMO_LOGINS=true).
 * Runs on its own on a live demo server without touching the menu or store settings:
 *   php artisan db:seed --class=DemoUsersSeeder --force
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            'superadmin@pos.test' => ['name' => 'المدير العام', 'role' => 'superadmin'],
            'admin@pos.test' => ['name' => 'المدير', 'role' => 'admin'],
            'cashier@pos.test' => ['name' => 'كاشير 1', 'role' => 'cashier'],
        ];

        foreach ($accounts as $email => $account) {
            User::updateOrCreate(['email' => $email], [...$account, 'password' => 'password', 'is_active' => true]);
        }
    }
}
