<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop's own photos on an order (flowers received, payment screenshots, the finished
     * piece), added in the dashboard and never shown to the customer.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('admin_images')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('admin_images');
        });
    }
};
