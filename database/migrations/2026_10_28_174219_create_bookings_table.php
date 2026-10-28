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
     * A person's place in a session: a student, or someone of the team the
     * front desk added. Taking the place moves class_sessions.booked_count
     * in the same transaction, see
     * docs/decisions/0005-cupo-garantizado-con-contador.md.
     *
     * The guarantees live in PostgreSQL:
     * - a person holds one current booking per session (partial unique
     *   index); after cancelling she can book again;
     * - a booking is cancelled exactly when it has its cancellation time.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['student', 'staff'])->default('student');
            $table->foreignId('booked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['confirmed', 'cancelled_on_time', 'cancelled_late', 'attended', 'no_show'])->default('confirmed');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'class_session_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX bookings_one_current_per_person
            ON bookings (class_session_id, user_id)
            WHERE status NOT IN ('cancelled_on_time', 'cancelled_late')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_cancelled_check
            CHECK ((status IN ('cancelled_on_time', 'cancelled_late')) = (cancelled_at IS NOT NULL))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
