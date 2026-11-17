<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The demo accounts behind the one-click login buttons (DEMO_LOGINS=true).
 * Runs on its own on a live demo server without touching the menu or store settings:
 *   php artisan db:seed --class=DemoUsersSeeder --force
 *
 * The password "password" is only set when an account is first created: running the seeder again
 * fixes names, roles and active flags but never resets a password that was changed.
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
            $user = User::firstOrNew(['email' => $email]);
            $user->fill([...$account, 'is_active' => true]);
            if (! $user->exists) {
                $user->password = 'password';
            }
            $user->save();
        }
    }
}
