<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attributes are pill buttons (one choice) unless set otherwise. Existing checkbox attributes
 * become pill buttons too, except the "Options" add-on lists, where customers pick several.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->string('type')->default('select')->change();
        });

        DB::table('product_attributes')
            ->where('type', 'checkbox')
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['options'])
            ->update(['type' => 'select']);
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->string('type')->default('checkbox')->change();
        });
    }
};
