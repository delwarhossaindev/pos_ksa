<?php

use App\Support\Arabic;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Arabic-normalised "name_ar name_en" used for searching (أ/إ/آ→ا, ة→ه, ى→ي, no tashkeel).
            $table->string('search_text', 400)->nullable()->after('name_ar');
        });

        DB::table('products')->orderBy('id')->each(function ($p) {
            DB::table('products')->where('id', $p->id)->update([
                'search_text' => Arabic::normalizeSearch($p->name_ar.' '.$p->name_en),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
