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
     * The weekly schedule: «pilates mat, Tuesdays at 19:00, with Fernanda».
     * The weekday is ISO (1 = Monday) and the time is local to the studio
     * (America/Santiago); sessions are generated from it in UTC. The
     * instructor is nullable: removing someone from the team leaves the slot
     * without one until it is reassigned.
     */
    public function up(): void
    {
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_type_id')->constrained()->restrictOnDelete();
            $table->smallInteger('weekday');
            $table->time('starts_at');
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['studio_id', 'weekday', 'starts_at']);
        });

        DB::statement('ALTER TABLE schedule_slots ADD CONSTRAINT schedule_slots_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_slots');
    }
};
