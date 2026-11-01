<?php

use App\Actions\Payments\ConfirmWebpayPayment;
use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\StartWebpayPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Thursday, October 29, in the afternoon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->plan = Plan::factory()->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30]);
    $this->javiera = User::factory()->create();
});

function startedWebpayPayment(Plan $plan, User $student): Payment
{
    Http::fake(['*/transactions' => Http::response(webpayCreated())]);

    return app(StartWebpayPayment::class)->handle($plan, $student);
}

it('starts a pending payment at the plan\'s price, with a transaction from Transbank', function () {
    Http::fake(['*/transactions' => Http::response(webpayCreated())]);

    $payment = app(StartWebpayPayment::class)->handle($this->plan, $this->javiera);

    expect($payment->refresh())
        ->status->toBe(PaymentStatus::Pending)
        ->method->toBe(PaymentMethod::Webpay)
        ->amount->toBe(45000)
        ->plan_id->toBe($this->plan->id)
        ->plan_purchase_id->toBeNull()
        ->cash_register_id->toBeNull()
        ->paid_at->toBeNull()
        ->webpay_token->toBe('tok-123')
        ->webpay_url->toBe('https://webpay3gint.transbank.cl/webpayserver/initTransaction')
        ->and(strlen((string) $payment->webpay_buy_order))->toBeLessThanOrEqual(26);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['amount'] === 45000
        && $request['buy_order'] === $payment->webpay_buy_order
        && $request['return_url'] === route('webpay.return')
        && $request->hasHeader('Tbk-Api-Key-Id', '597055555532'));
});

it('fails the payment, charging nothing, when Webpay does not answer', function () {
    Http::fake(['*/transactions' => Http::response(['error_message' => 'Not available'], 500)]);

    expect(fn () => app(StartWebpayPayment::class)->handle($this->plan, $this->javiera))
        ->toThrow(PaymentRejected::class, 'Webpay no responde en este momento. Prueba de nuevo en un rato: no se cobró nada.');
    expect(Payment::sole())
        ->status->toBe(PaymentStatus::Failed)
        ->status_reason->toBe('Webpay no respondió.');
});

it('sells no archived plan', function () {
    Http::fake();

    expect(fn () => app(StartWebpayPayment::class)->handle(Plan::factory()->archived()->create(), $this->javiera))
        ->toThrow(PaymentRejected::class, 'Este plan ya no está a la venta.');
    Http::assertNothingSent();
});

it('approves it into her plan from today, once, however many times it is confirmed', function () {
    $payment = startedWebpayPayment($this->plan, $this->javiera);
    Http::fake(['*/transactions/tok-123' => Http::response(webpayTransaction($payment))]);
    $this->plan->update(['price' => 50000]);

    app(ConfirmWebpayPayment::class)->handle($payment);
    app(ConfirmWebpayPayment::class)->handle($payment);

    $purchase = $this->javiera->planPurchases()->sole();
    expect($payment->refresh())
        ->status->toBe(PaymentStatus::Approved)
        ->plan_purchase_id->toBe($purchase->id)
        ->paid_at->toEqual(now())
        ->webpay_authorization_code->toBe('1213')
        ->card_last_digits->toBe('6623')
        ->and($purchase)
        ->price->toBe(45000)
        ->list_price->toBe(50000)
        ->sold_by_id->toBeNull()
        ->and($purchase->starts_on->toDateString())->toBe('2026-10-29');
    Http::assertSentCount(1);
});

it('reads how it stands when Transbank refuses to confirm it again', function () {
    $payment = startedWebpayPayment($this->plan, $this->javiera);
    Http::fake(fn (Request $request) => $request->method() === 'PUT'
        ? Http::response(['error_message' => 'Transaction already locked by another process'], 422)
        : Http::response(webpayTransaction($payment)));

    expect(app(ConfirmWebpayPayment::class)->handle($payment)->status)->toBe(PaymentStatus::Approved);
});

it('fails it when Webpay declined it, when she cancelled, or when the amount does not match', function (array $answer, bool $cancelled, string $reason) {
    $payment = startedWebpayPayment($this->plan, $this->javiera);
    Http::fake(['*/transactions/tok-123' => Http::response(webpayTransaction($payment, $answer))]);

    expect(app(ConfirmWebpayPayment::class)->handle($payment, $cancelled))
        ->status->toBe(PaymentStatus::Failed)
        ->status_reason->toBe($reason)
        ->plan_purchase_id->toBeNull()
        ->and($this->javiera->planPurchases()->exists())->toBeFalse();
})->with([
    'declined' => [['status' => 'FAILED', 'response_code' => -1], false, 'Webpay rechazó el pago.'],
    'cancelled' => [['status' => 'INITIALIZED', 'response_code' => null], true, 'Anulaste el pago en Webpay.'],
    'never paid' => [['status' => 'INITIALIZED', 'response_code' => null], false, 'No se completó el pago.'],
    'another amount' => [['amount' => 1000], false, 'El monto pagado no coincide con el plan.'],
]);

it('leaves it pending while Transbank does not answer', function () {
    $payment = startedWebpayPayment($this->plan, $this->javiera);
    Http::fake(['*' => Http::response([], 503)]);

    expect(app(ConfirmWebpayPayment::class)->handle($payment)->status)->toBe(PaymentStatus::Pending);
});

it('settles the payments left pending for 15 minutes, and not the newer ones', function () {
    Http::fake(['*/transactions' => Http::sequence()->push(webpayCreated())->push(webpayCreated('tok-new'))]);
    $old = app(StartWebpayPayment::class)->handle($this->plan, $this->javiera);
    $this->travel(16)->minutes();
    $new = app(StartWebpayPayment::class)->handle($this->plan, User::factory()->create());
    Http::fake([
        '*/transactions/tok-123' => Http::response(webpayTransaction($old)),
        '*' => Http::response(['error_message' => 'unexpected'], 500),
    ]);

    $this->artisan('webpay:reconcile')->expectsOutput('Webpay payments settled: 1.')->assertSuccessful();

    expect($old->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($new->refresh()->status)->toBe(PaymentStatus::Pending);
});
