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
     * A membership is a person in a studio with one role. The allowed roles
     * are listed explicitly (not read from App\Enums\StudioRole) so this
     * migration stays a fixed snapshot of the schema.
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'front_desk', 'instructor', 'student']);
            $table->timestampsTz();

            $table->unique(['studio_id', 'user_id']);
            $table->index('user_id');
        });

        // Each studio has a single owner, and a person owns a single studio
        // (Cupo has no branches). Laravel has no API for partial indexes, so
        // these are plain SQL.
        DB::statement("CREATE UNIQUE INDEX memberships_one_owner_per_studio ON memberships (studio_id) WHERE role = 'owner'");
        DB::statement("CREATE UNIQUE INDEX memberships_one_studio_per_owner ON memberships (user_id) WHERE role = 'owner'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
