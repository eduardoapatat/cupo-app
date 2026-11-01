<?php

use App\Enums\PaymentStatus;
use App\Enums\StudioRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create();
    $this->plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000]);
    Http::fake(['*/transactions' => Http::response(webpayCreated())]);
});

/**
 * Javiera starts paying the plan, as «Comprar plan» does.
 */
function payWithWebpay(object $test): Payment
{
    $test->actingAs($test->javiera)->post(route('studios.my-plan.webpay.store', $test->studio), ['plan_id' => $test->plan->id]);

    return Payment::query()->latest('id')->firstOrFail();
}

it('starts the payment and sends her to Webpay with its token', function () {
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.webpay.store', $this->studio), ['plan_id' => $this->plan->id])
        ->assertRedirect(route('studios.my-plan.webpay', [$this->studio, Payment::sole()]));

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.webpay', [$this->studio, Payment::sole()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/webpay-redirect')
            ->where('url', 'https://webpay3gint.transbank.cl/webpayserver/initTransaction')
            ->where('token', 'tok-123')
            ->where('amount', 45000));
});

it('validates the plan and keeps the way to Webpay to the student who started it', function () {
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.webpay.store', $this->studio), [])
        ->assertSessionHasErrors(['plan_id' => 'Elige el plan.']);
    $this->actingAs($this->javiera)
        ->post(route('studios.my-plan.webpay.store', $this->studio), ['plan_id' => Plan::factory()->create()->id])
        ->assertNotFound();

    $payment = payWithWebpay($this);

    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::Student)->create())
        ->get(route('studios.my-plan.webpay', [$this->studio, $payment]))
        ->assertNotFound();
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create())
        ->post(route('studios.my-plan.webpay.store', $this->studio), ['plan_id' => $this->plan->id])
        ->assertForbidden();
});

it('brings her back to «Mi plan» with her plan active', function () {
    $payment = payWithWebpay($this);
    Http::fake(['*/transactions/tok-123' => Http::response(webpayTransaction($payment))]);

    $this->actingAs($this->javiera)
        ->get(route('webpay.return', ['token_ws' => 'tok-123']))
        ->assertRedirect(route('studios.my-plan.index', $this->studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pagaste con Webpay. Tu plan ya está activo.']);
    $this->actingAs($this->javiera)
        ->get(route('webpay.return', ['token_ws' => 'tok-123']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pagaste con Webpay. Tu plan ya está activo.']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($this->javiera->planPurchases()->count())->toBe(1);
    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.webpay', [$this->studio, $payment]))
        ->assertRedirect(route('studios.my-plan.index', $this->studio));
});

it('tells her nothing was charged when she cancelled or the form timed out', function (Closure $query) {
    $payment = payWithWebpay($this);
    Http::fake(['*/transactions/tok-123' => Http::response(['error_message' => 'Invalid status'], 422)]);

    $this->actingAs($this->javiera)
        ->get(route('webpay.return', $query($payment)))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Anulaste el pago en Webpay: no se cobró nada.']);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('failedPayment', ['planName' => '8 clases al mes', 'reason' => 'Anulaste el pago en Webpay.'])
            ->where('canBuy', true));
    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
    Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
})->with([
    'cancelled' => [fn (Payment $payment) => ['TBK_TOKEN' => 'tok-123', 'TBK_ORDEN_COMPRA' => $payment->webpay_buy_order, 'TBK_ID_SESION' => (string) $payment->user_id]],
    'timed out' => [fn (Payment $payment) => ['TBK_ORDEN_COMPRA' => $payment->webpay_buy_order, 'TBK_ID_SESION' => (string) $payment->user_id]],
]);

it('answers 404 for a token of someone else, and asks to log in first', function () {
    $payment = payWithWebpay($this);

    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::Student)->create())
        ->get(route('webpay.return', ['token_ws' => 'tok-123']))
        ->assertNotFound();
    $this->actingAs($this->javiera)->get(route('webpay.return'))->assertNotFound();

    auth()->logout();
    $this->get(route('webpay.return', ['token_ws' => 'tok-123']))->assertRedirect(route('login'));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('turns the POST back from Webpay into a GET without touching her session', function () {
    $response = $this->post(route('webpay.return.post'), ['TBK_TOKEN' => 'tok-123', 'TBK_ORDEN_COMPRA' => 'cupo1', 'other' => 'x']);

    $response->assertStatus(303)
        ->assertRedirect(route('webpay.return', ['TBK_TOKEN' => 'tok-123', 'TBK_ORDEN_COMPRA' => 'cupo1']));
    expect($response->headers->getCookies())->toBeEmpty();
});

it('shows her a payment still pending at Webpay', function () {
    payWithWebpay($this);

    $this->actingAs($this->javiera)
        ->get(route('studios.my-plan.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('webpayPending', ['planName' => '8 clases al mes', 'amount' => 45000])
            ->where('failedPayment', null));
});
