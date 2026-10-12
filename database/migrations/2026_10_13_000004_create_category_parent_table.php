<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A category can sit under any number of parents, at any depth (a sub-category can have
 * sub-categories of its own). The links live in category_parent; categories.parent_id is kept
 * filled with the first parent for anything that still reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_parent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['category_id', 'parent_id']);
        });

        $links = DB::table('categories')
            ->whereNotNull('parent_id')
            ->whereColumn('parent_id', '!=', 'id')
            ->get(['id', 'parent_id']);
        foreach ($links->chunk(500) as $chunk) {
            DB::table('category_parent')->insert($chunk->map(fn ($c) => [
                'category_id' => $c->id,
                'parent_id' => $c->parent_id,
            ])->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_parent');
    }
};
