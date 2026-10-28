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
     * A person waiting for a spot in a full session. When a spot frees up
     * early enough, the first one waiting gets a booking in the same
     * transaction, see docs/decisions/0007-lista-de-espera-en-la-misma-transaccion.md.
     *
     * The guarantees live in PostgreSQL:
     * - a person waits once per session (partial unique index); after
     *   leaving she can join again;
     * - an entry is resolved exactly when it is no longer waiting.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['waiting', 'promoted', 'left'])->default('waiting');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['class_session_id', 'status', 'created_at']);
            $table->index('user_id');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX waitlist_entries_one_waiting_per_person
            ON waitlist_entries (class_session_id, user_id)
            WHERE status = 'waiting'
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE waitlist_entries ADD CONSTRAINT waitlist_entries_resolved_check
            CHECK ((status = 'waiting') = (resolved_at IS NULL))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
