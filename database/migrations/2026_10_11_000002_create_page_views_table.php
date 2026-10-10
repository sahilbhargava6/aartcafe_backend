<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-party storefront analytics. One row per event:
     * 'view' (page view), 'add_to_bag', or 'ping' (sent when a visitor leaves a page,
     * so session duration covers the time spent on the last page).
     */
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 64)->index();
            $table->string('session_id', 64)->index();
            $table->string('event', 20)->default('view');
            $table->string('path', 500);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referrer_host')->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
