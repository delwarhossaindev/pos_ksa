<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each e-invoice remembers the ZATCA environment (sandbox | simulation | production) it was signed in,
 * so test invoices never reach production and each environment keeps its own list and hash chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('environment')->default('sandbox')->after('order_id')->index();
        });

        $current = DB::table('zatca_settings')->value('environment');
        if ($current) {
            DB::table('invoices')->update(['environment' => $current]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['environment']);
            $table->dropColumn('environment');
        });
    }
};
