<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('daily_user_count')->default(0);
            $table->integer('weekly_user_count')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0.00); // e.g. 42.50%
            $table->integer('session_duration')->default(0); // in seconds
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_analytics');
    }
};
