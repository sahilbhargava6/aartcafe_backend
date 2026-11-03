<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Custom Request" a customer writes in the bag. It used to go only into the WhatsApp
     * message; now it is kept with the order too.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('custom_request')->nullable()->after('reference_image');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('custom_request');
        });
    }
};
