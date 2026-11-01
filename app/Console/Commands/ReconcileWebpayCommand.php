<?php

namespace App\Console\Commands;

use App\Actions\Payments\ConfirmWebpayPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpay:reconcile')]
#[Description('Settle the Webpay payments still pending after 15 minutes, of students who did not come back from paying')]
class ReconcileWebpayCommand extends Command
{
    /**
     * Execute the console command. It runs every 5 minutes. By 15 minutes
     * the Webpay form has expired (10 in integration, 4 in production), so
     * a payment still unpaid then will not be paid.
     */
    public function handle(ConfirmWebpayPayment $confirmWebpayPayment): int
    {
        $settled = 0;

        Payment::query()
            ->where('method', PaymentMethod::Webpay)
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(15))
            ->lazyById()
            ->each(function (Payment $payment) use ($confirmWebpayPayment, &$settled): void {
                if ($confirmWebpayPayment->handle($payment)->status !== PaymentStatus::Pending) {
                    $settled++;
                }
            });

        $this->info("Webpay payments settled: {$settled}.");

        return self::SUCCESS;
    }
}
