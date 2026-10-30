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
     * A student buys a plan from the app by bank transfer: she uploads the
     * receipt, and the payment waits «to review» with the plan she chose
     * (plan_id) and no purchase yet. Whoever reviews it approves it, which
     * creates the purchase, or rejects it saying why. It goes into no
     * register: the money never passed through the counter.
     *
     * The guarantees live in PostgreSQL:
     * - an approved or voided payment has its purchase, and one to review
     *   or rejected has none;
     * - one to review has the plan and the receipt, and a rejected one who
     *   reviewed it and why;
     * - a student has one payment to review at a time in each studio.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('plan_purchase_id')->nullable()->change();
            $table->foreignId('plan_id')->nullable()->after('plan_purchase_id')->constrained()->nullOnDelete();
            $table->string('receipt_path')->nullable()->after('received_by_id');
            $table->foreignId('reviewed_by_id')->nullable()->after('receipt_path')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by_id');
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->text('transfer_details')->nullable();
        });

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('to_review', 'approved', 'rejected', 'voided'))");
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT payments_purchase_check CHECK (
                (plan_purchase_id IS NOT NULL) = (status IN ('approved', 'voided'))
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT payments_review_check CHECK (
                (status <> 'to_review' OR (plan_id IS NOT NULL AND receipt_path IS NOT NULL))
                AND (status <> 'rejected' OR (reviewed_at IS NOT NULL AND status_reason IS NOT NULL))
            )
            SQL);
        DB::statement("CREATE UNIQUE INDEX payments_one_to_review_per_student ON payments (studio_id, user_id) WHERE status = 'to_review'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX payments_one_to_review_per_student');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_review_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_purchase_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('approved', 'voided'))");

        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn('transfer_details');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['receipt_path', 'reviewed_at']);
            $table->foreignId('plan_purchase_id')->nullable(false)->change();
        });
    }
};
