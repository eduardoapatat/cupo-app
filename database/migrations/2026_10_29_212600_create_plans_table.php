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
     * A plan the studio sells: a price in CLP (an integer, never a float),
     * a number of classes or none for an unlimited one, how many days it
     * lasts and how many classes a day it allows. A plan already sold is
     * archived, not deleted, because the purchases keep a copy of it.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('price');
            $table->smallInteger('credits')->nullable();
            $table->smallInteger('validity_days');
            $table->smallInteger('daily_limit')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_credits_check CHECK (credits IS NULL OR credits > 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_validity_check CHECK (validity_days > 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_daily_limit_check CHECK (daily_limit IS NULL OR daily_limit > 0)');
        DB::statement('CREATE UNIQUE INDEX plans_name_per_studio ON plans (studio_id, lower(name)) WHERE archived_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
