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
     * A kind of class the studio teaches (pilates mat, reformer, yoga), with
     * its length and how many people fit. A class type that is no longer
     * taught is deactivated, not deleted, because past sessions point to it.
     */
    public function up(): void
    {
        Schema::create('class_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->smallInteger('duration_minutes');
            $table->smallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE class_types ADD CONSTRAINT class_types_duration_check CHECK (duration_minutes > 0 AND duration_minutes <= 480)');
        DB::statement('ALTER TABLE class_types ADD CONSTRAINT class_types_capacity_check CHECK (capacity > 0)');
        DB::statement('CREATE UNIQUE INDEX class_types_name_per_studio ON class_types (studio_id, lower(name))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_types');
    }
};
