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
     * A payment taken by mistake is voided, never deleted: it keeps who
     * voided it, when and why. Voiding it voids the plan it paid for too
     * (plan_purchases.cancelled_at). Voiding changes the status, not the
     * register, so a register closed before keeps its totals.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('voided_by_id')->nullable()->after('received_by_id')->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable()->after('voided_by_id');
            $table->text('status_reason')->nullable()->after('voided_at');
        });

        Schema::table('plan_purchases', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable()->after('price_reason');
        });

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('approved', 'voided'))");
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT payments_voided_check CHECK (
                (status = 'voided') = (voided_at IS NOT NULL)
                AND (status <> 'voided' OR status_reason IS NOT NULL)
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_voided_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('approved'))");

        Schema::table('plan_purchases', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_id');
            $table->dropColumn(['voided_at', 'status_reason']);
        });
    }
};
