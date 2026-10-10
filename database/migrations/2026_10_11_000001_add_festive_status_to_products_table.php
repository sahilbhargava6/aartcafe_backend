<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * festive_status: 'featured' (homepage Festive Treasures + Festive Specials page),
     * 'selected' (Festive Specials page), 'hidden' (off-festival items, price on request)
     * or null. is_festive_special stays true for featured/selected.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('festive_status')->nullable();
        });

        // Products already marked festive were shown in Festive Treasures on the homepage
        DB::table('products')->where('is_festive_special', true)->update(['festive_status' => 'featured']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('festive_status');
        });
    }
};
