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
     * When the money came in, which is the day income counts on: at the
     * counter when it was sold, and a transfer from the app when it was
     * approved, not when the receipt was sent. A payment has it exactly when
     * it was approved, even if it was voided later.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('paid_at')->nullable()->after('status');
            $table->index(['studio_id', 'paid_at']);
        });

        DB::statement("UPDATE payments SET paid_at = COALESCE(reviewed_at, created_at) WHERE status IN ('approved', 'voided')");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_paid_check CHECK ((paid_at IS NOT NULL) = (status IN ('approved', 'voided')))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_paid_check');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['studio_id', 'paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
