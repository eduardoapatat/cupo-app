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
     * A concrete class: «pilates mat, Tuesday 4 at 19:00». It is called
     * class_sessions because Laravel already owns the sessions table.
     *
     * The guarantees live in PostgreSQL, see
     * docs/decisions/0005-cupo-garantizado-con-contador.md:
     * - booked_count never goes above capacity (CHECK), so a spot is never
     *   oversold, whatever writes the row;
     * - the studio has one room, so two active sessions never overlap
     *   (exclusion constraint on the generated period);
     * - generating the schedule twice never duplicates a session (unique
     *   slot and start).
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->rawColumn('period', 'tstzrange')->storedAs("tstzrange(starts_at, ends_at, '[)')");
            $table->smallInteger('capacity');
            $table->smallInteger('booked_count')->default(0);
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['scheduled', 'cancelled'])->default('scheduled');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['schedule_slot_id', 'starts_at']);
            $table->index(['studio_id', 'starts_at']);
            $table->index(['instructor_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_range_check CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_capacity_check CHECK (capacity > 0)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_booked_count_check CHECK (booked_count >= 0 AND booked_count <= capacity)');
        DB::statement(<<<'SQL'
            ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_no_overlap
            EXCLUDE USING gist (studio_id WITH =, period WITH &&)
            WHERE (status = 'scheduled')
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
