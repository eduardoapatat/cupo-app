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
     * The register a payment taken at the counter went into: the open one
     * of whoever took it. A closed register takes no payments in and lets
     * none out, guaranteed by a trigger that reads the register FOR SHARE,
     * so closing it (FOR UPDATE) waits for a sale in progress, and a sale
     * that comes after the close is rejected (see
     * docs/decisions/0009-la-caja-cerrada-no-recibe-pagos.md).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->after('plan_purchase_id')->constrained()->cascadeOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION payments_need_an_open_register() RETURNS trigger AS $$
            DECLARE
                register_closed_at timestamptz;
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.cash_register_id IS NOT NULL THEN
                    SELECT closed_at INTO register_closed_at FROM cash_registers WHERE id = OLD.cash_register_id FOR SHARE;

                    IF register_closed_at IS NOT NULL THEN
                        RAISE EXCEPTION 'cash register % is closed', OLD.cash_register_id USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF NEW.cash_register_id IS NOT NULL THEN
                    SELECT closed_at INTO register_closed_at FROM cash_registers WHERE id = NEW.cash_register_id FOR SHARE;

                    IF register_closed_at IS NOT NULL THEN
                        RAISE EXCEPTION 'cash register % is closed', NEW.cash_register_id USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payments_need_an_open_register
                BEFORE INSERT OR UPDATE OF cash_register_id ON payments
                FOR EACH ROW
                EXECUTE FUNCTION payments_need_an_open_register();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payments_need_an_open_register ON payments');
        DB::unprepared('DROP FUNCTION IF EXISTS payments_need_an_open_register()');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }
};
