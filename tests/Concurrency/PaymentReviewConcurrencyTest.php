<?php

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\ReviewPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency, as in BookSessionConcurrencyTest: the front desk and
 * the owner approve the same transfer receipt at the same moment. Only one
 * of them does, and the student gets one plan, not two.
 */
/**
 * Approving the payment, to run in its own process. Built outside the test
 * class, so the closure carries no class the process cannot load.
 */
function approvalInAnotherProcess(int $paymentId, int $reviewerId, float $startAt): Closure
{
    return static function () use ($paymentId, $reviewerId, $startAt): string {
        time_sleep_until($startAt);

        try {
            app(ReviewPayment::class)->approve(Payment::findOrFail($paymentId), User::findOrFail($reviewerId));

            return 'approved';
        } catch (PaymentRejected) {
            return 'rejected';
        }
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('approves a receipt once when two people approve it at once', function () {
    $plan = Plan::factory()->create(['price' => 45000]);
    $student = User::factory()->create();
    $payment = new Payment([
        'studio_id' => $plan->studio_id,
        'plan_id' => $plan->id,
        'user_id' => $student->id,
        'amount' => 45000,
        'method' => PaymentMethod::Transfer,
        'receipt_path' => 'receipts/comprobante.png',
    ]);
    $payment->forceFill(['status' => PaymentStatus::ToReview])->save();
    $reviewers = User::factory()->count(2)->create();
    $startAt = microtime(true) + 2;

    $results = Concurrency::driver('process')->run($reviewers->map(
        fn (User $reviewer): Closure => approvalInAnotherProcess($payment->id, $reviewer->id, $startAt),
    )->all());
    sort($results);

    expect($results)->toBe(['approved', 'rejected'])
        ->and($student->planPurchases()->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Approved);
});
