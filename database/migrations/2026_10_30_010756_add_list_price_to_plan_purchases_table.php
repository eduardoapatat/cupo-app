<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The plan's price when it was sold, beside the price charged, so a
     * changed price or a gift shows how much it was worth. Null for sales
     * made before it existed.
     */
    public function up(): void
    {
        Schema::table('plan_purchases', function (Blueprint $table) {
            $table->integer('list_price')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_purchases', function (Blueprint $table) {
            $table->dropColumn('list_price');
        });
    }
};
