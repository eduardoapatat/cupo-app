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
     * A plan a student bought. It keeps a copy of the plan as it was sold,
     * so editing the plan later never changes it. credits_left is the count
     * of classes she still has, taken with a conditional UPDATE when she
     * books (see docs/decisions/0008-clases-del-plan-con-update-condicional.md);
     * an unlimited plan has neither credits nor credits_left.
     *
     * The guarantees live in PostgreSQL:
     * - she never has fewer than 0 classes left, nor more than she bought;
     * - it lasts from starts_on until expires_at, the start of the day after
     *   its last day in the studio's time zone.
     */
    public function up(): void
    {
        Schema::create('plan_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->integer('price');
            $table->smallInteger('credits')->nullable();
            $table->smallInteger('credits_left')->nullable();
            $table->smallInteger('validity_days');
            $table->smallInteger('daily_limit')->nullable();
            $table->date('starts_on');
            $table->timestampTz('expires_at');
            $table->foreignId('sold_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('price_reason')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'studio_id', 'expires_at']);
        });

        DB::statement('ALTER TABLE plan_purchases ADD CONSTRAINT plan_purchases_price_check CHECK (price >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE plan_purchases ADD CONSTRAINT plan_purchases_credits_check
            CHECK ((credits IS NULL AND credits_left IS NULL) OR (credits IS NOT NULL AND credits_left IS NOT NULL AND credits > 0 AND credits_left BETWEEN 0 AND credits))
            SQL);
        DB::statement('ALTER TABLE plan_purchases ADD CONSTRAINT plan_purchases_validity_check CHECK (validity_days > 0 AND expires_at > starts_on)');
        DB::statement('ALTER TABLE plan_purchases ADD CONSTRAINT plan_purchases_daily_limit_check CHECK (daily_limit IS NULL OR daily_limit > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_purchases');
    }
};
