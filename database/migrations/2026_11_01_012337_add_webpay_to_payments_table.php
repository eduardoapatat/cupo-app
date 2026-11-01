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
     * A student pays a plan online with Webpay Plus. The payment is pending
     * with the plan she chose while she pays at Transbank, and then it is
     * approved (which creates her purchase) or it fails, saying why. Its
     * buy order and token find it again when she comes back, and the URL
     * is where her browser pays it. It goes into no register, like a
     * transfer from the app.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('webpay_buy_order', 26)->nullable()->unique()->after('receipt_path');
            $table->string('webpay_token', 64)->nullable()->unique()->after('webpay_buy_order');
            $table->string('webpay_url')->nullable()->after('webpay_token');
            $table->string('webpay_authorization_code', 6)->nullable()->after('webpay_token');
            $table->string('card_last_digits', 4)->nullable()->after('webpay_authorization_code');
        });

        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'card', 'transfer', 'webpay'))");
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('to_review', 'approved', 'rejected', 'voided', 'pending', 'failed'))");
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT payments_webpay_check CHECK (
                (method = 'webpay') = (webpay_buy_order IS NOT NULL)
                AND (status NOT IN ('pending', 'failed') OR (method = 'webpay' AND plan_id IS NOT NULL))
                AND (status <> 'failed' OR status_reason IS NOT NULL)
                AND (method <> 'webpay' OR cash_register_id IS NULL)
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM payments WHERE method = 'webpay'");
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_webpay_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('to_review', 'approved', 'rejected', 'voided'))");
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_method_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'card', 'transfer'))");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['webpay_buy_order', 'webpay_token', 'webpay_url', 'webpay_authorization_code', 'card_last_digits']);
        });
    }
};
