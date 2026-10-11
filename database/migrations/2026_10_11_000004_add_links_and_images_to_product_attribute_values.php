<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Available only with" links between option values (any number per value, replacing the
 * single parent_id link, which is copied over) and a gallery photo per option value.
 * parent_id stays in the table but is no longer read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_value_links', function (Blueprint $table) {
            $table->foreignId('value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->foreignId('linked_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->primary(['value_id', 'linked_value_id']);
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->string('image', 1024)->nullable()->after('price_modifier');
        });

        $links = DB::table('product_attribute_values')
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->map(fn ($value) => ['value_id' => $value->id, 'linked_value_id' => $value->parent_id])
            ->all();
        foreach (array_chunk($links, 500) as $chunk) {
            DB::table('product_attribute_value_links')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        Schema::dropIfExists('product_attribute_value_links');
    }
};
