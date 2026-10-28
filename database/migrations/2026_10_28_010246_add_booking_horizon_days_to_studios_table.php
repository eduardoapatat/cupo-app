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
     * How many days ahead students can book. Existing studios get the value
     * config/studio.php had when this rule appeared (14), and the column is
     * required from then on.
     */
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->smallInteger('booking_horizon_days')->nullable();
        });

        DB::table('studios')->update(['booking_horizon_days' => 14]);

        Schema::table('studios', function (Blueprint $table) {
            $table->smallInteger('booking_horizon_days')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE studios ADD CONSTRAINT studios_booking_horizon_check CHECK (booking_horizon_days > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn('booking_horizon_days');
        });
    }
};
