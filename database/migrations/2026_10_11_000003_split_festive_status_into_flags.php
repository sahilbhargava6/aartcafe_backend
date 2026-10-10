<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Featured (homepage Festive Treasures) and Selected (Festive Specials page) can now
     * be combined, so the single festive_status value becomes separate flags. Hidden
     * (price on request) stays exclusive; the Product model enforces that.
     *
     * festive_status is left in place so the previous release keeps working while this
     * deploy switches over; it is no longer read and can be dropped in a later migration.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_festive_featured')->default(false);
            $table->boolean('is_festive_selected')->default(false);
            $table->boolean('is_festive_hidden')->default(false);
        });

        // Featured items were also listed on the Festive Specials page, so keep both
        DB::table('products')->where('festive_status', 'featured')->update(['is_festive_featured' => true, 'is_festive_selected' => true]);
        DB::table('products')->where('festive_status', 'selected')->update(['is_festive_selected' => true]);
        DB::table('products')->where('festive_status', 'hidden')->update(['is_festive_hidden' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_festive_featured', 'is_festive_selected', 'is_festive_hidden']);
        });
    }
};
