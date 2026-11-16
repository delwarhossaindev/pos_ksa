<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BT-114 Rounding amount (cbc:PayableRoundingAmount): what turns the VAT-inclusive total into the amount
 * actually paid. A till price that includes VAT cannot always be split into net + 15% VAT to the halala
 * (e.g. 10.00 → 8.70 + 1.31 = 10.01), so the e-invoice of a POS sale carries the ±0.01 here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('rounding_amount', 14, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('rounding_amount');
        });
    }
};
