<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A student buys a plan from the app by bank transfer: she uploads the
 * receipt and the payment waits to be reviewed. Her plan starts when the
 * front desk or the owner approves it. She has one payment to review at a
 * time.
 */
class SubmitTransferReceipt
{
    public function __construct(private StoreReceipt $storeReceipt) {}

    /**
     * @throws PaymentRejected when the studio takes no transfers, the plan is not on sale or she already has one to review.
     */
    public function handle(Plan $plan, User $student, UploadedFile $receipt): Payment
    {
        $studio = $plan->studio;

        if (blank($studio->transfer_details)) {
            throw new PaymentRejected(__('The studio does not take transfers from the app yet.'));
        }

        if ($plan->isArchived()) {
            throw new PaymentRejected(__('This plan is no longer on sale.'));
        }

        if ($studio->payments()->where('user_id', $student->id)->where('status', PaymentStatus::ToReview)->exists()) {
            throw new PaymentRejected(__('You already have a receipt waiting to be reviewed.'));
        }

        $path = $this->storeReceipt->handle($studio, $receipt);

        try {
            $payment = new Payment([
                'studio_id' => $studio->id,
                'plan_id' => $plan->id,
                'user_id' => $student->id,
                'amount' => $plan->price,
                'method' => PaymentMethod::Transfer,
                'receipt_path' => $path,
            ]);
            $payment->forceFill(['status' => PaymentStatus::ToReview])->save();

            return $payment;
        } catch (UniqueConstraintViolationException) {
            // Sent twice at once: the other one is already waiting.
            Storage::delete($path);

            throw new PaymentRejected(__('You already have a receipt waiting to be reviewed.'));
        }
    }
}
