<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The classes a plan had left when it expired leave it in one movement,
     * so her balance goes to 0 and the history says how many she lost. It
     * is the only movement that takes more than one class at a time.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE credit_movements DROP CONSTRAINT credit_movements_reason_check');
        DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_reason_check CHECK (reason IN ('booking', 'cancelled_on_time', 'session_cancelled', 'returned', 'expired'))");
        DB::statement('ALTER TABLE credit_movements DROP CONSTRAINT credit_movements_delta_check');
        DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_delta_check CHECK (delta IN (-1, 1) OR (reason = 'expired' AND delta < 0))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM credit_movements WHERE reason = 'expired'");
        DB::statement('ALTER TABLE credit_movements DROP CONSTRAINT credit_movements_delta_check');
        DB::statement('ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_delta_check CHECK (delta IN (-1, 1))');
        DB::statement('ALTER TABLE credit_movements DROP CONSTRAINT credit_movements_reason_check');
        DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_reason_check CHECK (reason IN ('booking', 'cancelled_on_time', 'session_cancelled', 'returned'))");
    }
};
