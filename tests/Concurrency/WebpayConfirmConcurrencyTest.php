<?php

use App\Actions\Payments\ConfirmWebpayPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;

/**
 * Real concurrency, as in PaymentReviewConcurrencyTest: the student comes
 * back from Webpay at the same moment webpay:reconcile picks up her
 * payment. Only one of them confirms it with Transbank, and she gets one
 * plan, not two.
 */
/**
 * Confirming the payment, to run in its own process with Transbank faked
 * there. Returns how many times that process confirmed it with Transbank.
 * Built outside the test class, so the closure carries no class the
 * process cannot load.
 */
function confirmationInAnotherProcess(int $paymentId, float $startAt): Closure
{
    return static function () use ($paymentId, $startAt): int {
        $payment = Payment::findOrFail($paymentId);
        Http::fake(['*' => Http::response([
            'amount' => $payment->amount,
            'status' => 'AUTHORIZED',
            'buy_order' => $payment->webpay_buy_order,
            'card_detail' => ['card_number' => '6623'],
            'authorization_code' => '1213',
            'response_code' => 0,
        ])]);
        time_sleep_until($startAt);

        app(ConfirmWebpayPayment::class)->handle($payment);

        return Http::recorded(fn ($request) => $request->method() === 'PUT')->count();
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('confirms a Webpay payment once when two processes confirm it at once', function () {
    $plan = Plan::factory()->create(['price' => 45000]);
    $student = User::factory()->create();
    $payment = new Payment([
        'studio_id' => $plan->studio_id,
        'plan_id' => $plan->id,
        'user_id' => $student->id,
        'amount' => 45000,
        'method' => PaymentMethod::Webpay,
        'webpay_buy_order' => 'cupo261029TEST',
    ]);
    $payment->forceFill(['status' => PaymentStatus::Pending, 'webpay_token' => 'tok-123'])->save();
    $startAt = microtime(true) + 2;

    $commits = Concurrency::driver('process')->run([
        confirmationInAnotherProcess($payment->id, $startAt),
        confirmationInAnotherProcess($payment->id, $startAt),
    ]);

    expect(array_sum($commits))->toBe(1)
        ->and($student->planPurchases()->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Approved);
});
