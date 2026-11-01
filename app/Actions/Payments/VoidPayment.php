<?php

namespace App\Actions\Payments;

use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlanPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Void a payment taken by mistake, saying why. It voids the plan it paid
 * for: the classes left are gone, and its bookings of classes still to come
 * are cancelled, their spots going to the waitlist. Classes already taken
 * stay in her history. A Webpay payment gives the money back to her card
 * first, and is not voided if Transbank refuses. Who may void it
 * (payments.void_open or payments.void_closed) is checked by the caller.
 */
class VoidPayment
{
    public function __construct(
        private CancelBooking $cancelBooking,
        private RefundWebpayPayment $refundWebpayPayment,
    ) {}

    /**
     * @throws PaymentRejected when it is no longer approved, or Transbank does not give its money back.
     */
    public function handle(Payment $payment, User $by, string $reason): Payment
    {
        // Retried on a deadlock: the plan is locked before the classes its
        // bookings belong to, the other way around from a booking, which
        // only crosses with that same student booking at that moment.
        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $voided = Payment::query()
                ->whereKey($payment->id)
                ->where('status', PaymentStatus::Approved)
                ->update([
                    'status' => PaymentStatus::Voided,
                    'voided_by_id' => $by->id,
                    'voided_at' => now(),
                    'status_reason' => $reason,
                    'updated_at' => now(),
                ]);

            if ($voided === 0) {
                throw new PaymentRejected(__('This payment was already voided.'));
            }

            // The payment's row stays locked by the update, so a second void
            // waits here and then finds it voided: one refund.
            if ($payment->method === PaymentMethod::Webpay) {
                $this->refundWebpayPayment->handle($payment);
                Payment::query()->whereKey($payment->id)->update(['refunded_at' => now()]);
            }

            // Locked, so no booking takes a class from it from now on.
            $purchase = PlanPurchase::query()->whereKey($payment->plan_purchase_id)->lockForUpdate()->firstOrFail();
            $purchase->forceFill(['cancelled_at' => now()])->save();

            foreach ($this->upcomingBookings($purchase) as $booking) {
                $this->cancelBooking->handle($booking, $by, returnClass: true, reason: __('Her plan was voided.'));
            }

            return $payment->refresh();
        }, attempts: 3);
    }

    /**
     * The bookings of the plan for classes that have not started.
     *
     * @return Collection<int, Booking>
     */
    public function upcomingBookings(PlanPurchase $purchase): Collection
    {
        return $purchase->bookings()
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('classSession', fn (Builder $session) => $session->where('starts_at', '>', now()))
            ->with('classSession.studio')
            ->get();
    }
}
