<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settings (store, users, ZATCA, e-invoices) moved from "admin" to the new "superadmin" role.
 * So an existing install is never left without anyone who can open Settings, the oldest
 * active admin becomes the superadmin — unless a superadmin already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('role', 'superadmin')->exists()) {
            return;
        }

        $first = DB::table('users')->where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');

        if ($first) {
            DB::table('users')->where('id', $first)->update(['role' => 'superadmin']);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
    }
};
