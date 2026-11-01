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
     * Voiding a Webpay payment gives the money back to her card through
     * Transbank first; refunded_at says when. Only a voided Webpay payment
     * has it.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('refunded_at')->nullable()->after('voided_at');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_refunded_check CHECK (refunded_at IS NULL OR (status = 'voided' AND method = 'webpay'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_refunded_check');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('refunded_at');
        });
    }
};
