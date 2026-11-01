<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Webpay\WebpayClient;
use App\Webpay\WebpayException;
use Illuminate\Support\Facades\Log;

/**
 * Gives the money of a Webpay payment back to her card, as voiding it
 * does. If Transbank already gave it back (a retried void), it is not
 * asked twice; if Transbank refuses, nothing is voided.
 */
class RefundWebpayPayment
{
    public function __construct(private WebpayClient $webpay) {}

    /**
     * @throws PaymentRejected when Transbank does not give it back.
     */
    public function handle(Payment $payment): void
    {
        $token = $payment->webpay_token ?? throw new PaymentRejected(__('Transbank did not accept the refund, so nothing was voided.'));

        try {
            if ($this->webpay->status($token)->isRefunded()) {
                return;
            }
        } catch (WebpayException) {
            // Its status could not be read: ask for the refund anyway.
        }

        try {
            $this->webpay->refund($token, $payment->amount);
        } catch (WebpayException $exception) {
            Log::warning('Transbank refused a refund.', ['payment' => $payment->id, 'error' => $exception->getMessage()]);

            throw new PaymentRejected(__('Transbank did not accept the refund, so nothing was voided.'));
        }
    }
}
