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
     * The slug identifies the studio in its URLs (/estudios/{slug}) and the
     * enrollment token in its public sign-up link, which the owner can renew.
     * The rules start as a copy of config/studio.php; PostgreSQL has no
     * unsigned integers, so CHECK constraints keep them from going negative.
     */
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('commune');
            $table->string('enrollment_token', 64)->unique();
            $table->smallInteger('cancellation_window_hours');
            $table->smallInteger('booking_cutoff_minutes');
            $table->smallInteger('waitlist_cutoff_hours');
            $table->smallInteger('plan_expiry_warning_days');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE studios ADD CONSTRAINT studios_rules_check CHECK (cancellation_window_hours >= 0 AND booking_cutoff_minutes >= 0 AND waitlist_cutoff_hours >= 0 AND plan_expiry_warning_days >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studios');
    }
};
