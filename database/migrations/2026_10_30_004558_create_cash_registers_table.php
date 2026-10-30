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
     * The cash register of one person of the team on one day: the cash she
     * started with, and when she closes it, the cash she counted and the
     * totals per method, frozen. It points to the person, not to her
     * membership, so it keeps who ran it after she leaves the team.
     *
     * The guarantees live in PostgreSQL:
     * - one register per person and day, and only one open at a time;
     * - a closed register has its count and totals, and a note when the
     *   counted cash is not the expected one;
     * - a closed register never changes (see
     *   docs/decisions/0009-la-caja-cerrada-no-recibe-pagos.md).
     */
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->integer('opening_cash');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->integer('expected_cash')->nullable();
            $table->integer('counted_cash')->nullable();
            $table->integer('cash_total')->nullable();
            $table->integer('card_total')->nullable();
            $table->integer('transfer_total')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestampsTz();

            $table->unique(['studio_id', 'user_id', 'business_date']);
            $table->index(['studio_id', 'business_date']);
        });

        DB::statement('CREATE UNIQUE INDEX cash_registers_one_open_per_person ON cash_registers (studio_id, user_id) WHERE closed_at IS NULL');
        DB::statement('ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_opening_cash_check CHECK (opening_cash >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_closing_check CHECK (
                (closed_at IS NULL AND expected_cash IS NULL AND counted_cash IS NULL
                    AND cash_total IS NULL AND card_total IS NULL AND transfer_total IS NULL)
                OR (closed_at IS NOT NULL AND expected_cash IS NOT NULL AND counted_cash IS NOT NULL
                    AND cash_total IS NOT NULL AND card_total IS NOT NULL AND transfer_total IS NOT NULL
                    AND counted_cash >= 0 AND cash_total >= 0 AND card_total >= 0 AND transfer_total >= 0)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_difference_note_check CHECK (
                closed_at IS NULL OR counted_cash = expected_cash OR closing_note IS NOT NULL
            )
            SQL);

        // Only who ran it may change on a closed register: her account can
        // be deleted (user_id becomes null), its money cannot.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION cash_registers_keep_closed() RETURNS trigger AS $$
            BEGIN
                IF (NEW.studio_id, NEW.business_date, NEW.opening_cash, NEW.opened_at, NEW.closed_at,
                    NEW.expected_cash, NEW.counted_cash, NEW.cash_total, NEW.card_total, NEW.transfer_total, NEW.closing_note)
                   IS DISTINCT FROM
                   (OLD.studio_id, OLD.business_date, OLD.opening_cash, OLD.opened_at, OLD.closed_at,
                    OLD.expected_cash, OLD.counted_cash, OLD.cash_total, OLD.card_total, OLD.transfer_total, OLD.closing_note)
                THEN
                    RAISE EXCEPTION 'cash register % is closed', OLD.id USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER cash_registers_keep_closed
                BEFORE UPDATE ON cash_registers
                FOR EACH ROW WHEN (OLD.closed_at IS NOT NULL)
                EXECUTE FUNCTION cash_registers_keep_closed();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
        DB::unprepared('DROP FUNCTION IF EXISTS cash_registers_keep_closed()');
    }
};
