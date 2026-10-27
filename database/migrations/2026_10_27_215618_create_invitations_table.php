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
     * An invitation from the owner to join the studio's team, sent by email.
     * Only staff roles are invited: the owner is whoever creates the studio,
     * and students join through the enrollment link. Emails are stored in
     * lowercase, so a person cannot hold two pending invitations to the same
     * studio by changing the case.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['front_desk', 'instructor']);
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_email_lowercase_check CHECK (email = lower(email))');
        DB::statement('CREATE UNIQUE INDEX invitations_one_pending_per_email ON invitations (studio_id, email) WHERE accepted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
