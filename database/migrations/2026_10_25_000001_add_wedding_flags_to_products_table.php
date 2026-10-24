<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Split wedding special into:
     * - is_wedding_featured: shown in Wedding Specials on Homepage and Special Offers
     * - is_wedding_selected: shown on the Wedding Celebration page
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_wedding_featured')->default(false);
            $table->boolean('is_wedding_selected')->default(false);
        });

        // Initialize from existing is_wedding_special
        if (Schema::hasColumn('products', 'is_wedding_special')) {
            DB::table('products')->where('is_wedding_special', true)->update([
                'is_wedding_featured' => true,
                'is_wedding_selected' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_wedding_featured', 'is_wedding_selected']);
        });
    }
};
