<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A student enrolled at the counter may not use the app: she has a name
     * and a phone, and maybe an email, but no password until she activates
     * her account. PostgreSQL allows several NULLs under the unique email
     * index, so many students can have no email.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('phone', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations. It fails while a user has no email or no
     * password, which is the honest outcome: those rows need a decision.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
