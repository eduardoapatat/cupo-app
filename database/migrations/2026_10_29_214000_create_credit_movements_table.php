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
     * Every class taken from or returned to a plan, with why and who did
     * it, so the student and the studio can follow her balance. Unlimited
     * plans have no balance, so they leave no movements.
     */
    public function up(): void
    {
        Schema::create('credit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('delta');
            $table->enum('reason', ['booking', 'cancelled_on_time', 'session_cancelled', 'returned']);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->index(['plan_purchase_id', 'created_at']);
        });

        DB::statement('ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_delta_check CHECK (delta IN (-1, 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_movements');
    }
};
