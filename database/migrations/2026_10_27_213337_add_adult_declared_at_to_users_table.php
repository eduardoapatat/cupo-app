<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the person declared being 18 or older. Cupo does not store birth
     * dates, and it does not take minors. Accounts created before this column
     * existed never declared it, so it stays null for them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('adult_declared_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('adult_declared_at');
        });
    }
};
