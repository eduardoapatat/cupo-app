<?php

use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Payments\SubmitTransferReceipt;
use App\Actions\Payments\VoidPayment;
use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StudioRole;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->camila = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
    $this->carla = User::factory()->memberOf($this->studio, StudioRole::Owner)->create();
    $this->rosa = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Rosa Fuentes']);
    $this->register = CashRegister::factory()->for($this->studio)->for($this->camila)->create();
    $plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000]);
    $this->payment = app(SellPlan::class)->handle($plan, $this->rosa, $this->register, PaymentMethod::Cash, 40000, 'Amiga')->payments()->sole();
});

it('lists the payments by status, searching by student, with the totals', function () {
    $javiera = User::factory()->memberOf($this->studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $voided = app(SellPlan::class)->handle(Plan::factory()->for($this->studio)->create(), $javiera, $this->register, PaymentMethod::Card)->payments()->sole();
    app(VoidPayment::class)->handle($voided, $this->camila, 'Error');
    Payment::factory()->create(['amount' => 99000]);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/payments')
            ->has('payments.data', 2)
            ->where('stats', ['today' => 40000, 'month' => 40000, 'voidedThisMonth' => 1]));
    $this->actingAs($this->camila)
        ->get(route('studios.payments.index', [$this->studio, 'estado' => 'anulados']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'anulados')
            ->where('payments.data', fn ($payments) => collect($payments)->pluck('studentName')->all() === ['Javiera Morales']));
    $this->actingAs($this->camila)
        ->get(route('studios.payments.index', [$this->studio, 'buscar' => 'rosa']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.studentName', 'Rosa Fuentes')
            ->where('payments.data.0.planName', '8 clases al mes')
            ->has('payments.data', 1));
});

it('counts a transfer on the day it was approved, not the day it was sent', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 20:00', 'America/Santiago'));
    $payment = receiptToReview($this->studio);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'America/Santiago'));
    app(ReviewPayment::class)->approve($payment, $this->camila);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page->where('stats.today', 28000)->where('stats.month', 28000));
});

it('shows a payment with its plan, its register and the price it was sold at', function () {
    $this->actingAs($this->camila)
        ->get(route('studios.payments.show', [$this->studio, $this->payment]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/payment')
            ->where('payment.amount', 40000)
            ->where('payment.receivedBy', 'Camila Soto')
            ->where('payment.register.isClosed', false)
            ->where('payment.plan.listPrice', 45000)
            ->where('payment.plan.priceReason', 'Amiga')
            ->where('payment.plan.upcomingBookings', 0)
            ->where('canVoid', true)
            ->where('voidDenied', null));
});

it('lets the front desk void a payment while its register is open, saying why', function () {
    $route = route('studios.payments.void.store', [$this->studio, $this->payment]);

    $this->actingAs($this->camila)
        ->post($route, ['reason' => ' '])
        ->assertSessionHasErrors(['reason' => 'Escribe por qué lo anulas.']);
    $this->actingAs($this->camila)
        ->post($route, ['reason' => 'Se vendió el plan equivocado'])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Anulamos el pago y su plan.']);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Voided);
});

it('leaves a payment of a closed register to the owner', function () {
    app(CloseCashRegister::class)->handle($this->register, 60000);
    $route = route('studios.payments.void.store', [$this->studio, $this->payment]);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.show', [$this->studio, $this->payment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canVoid', false)
            ->where('voidDenied', 'Su caja ya está cerrada: solo la dueña puede anularlo.'));
    $this->actingAs($this->camila)->post($route, ['reason' => 'Error'])->assertForbidden();
    $this->actingAs($this->carla)->post($route, ['reason' => 'Error'])->assertRedirect();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Voided);
    $this->actingAs($this->carla)
        ->get(route('studios.payments.show', [$this->studio, $this->payment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.voidedAfterClosing', true)
            ->where('payment.plan.isCancelled', true)
            ->where('canVoid', false));
});

it('leaves a Webpay payment to the owner, and shows what Transbank answered', function () {
    $payment = Payment::factory()->create([
        'plan_purchase_id' => PlanPurchase::factory()->for($this->studio),
        'method' => PaymentMethod::Webpay,
        'webpay_buy_order' => 'cupo261029ABC',
        'webpay_authorization_code' => '1213',
        'card_last_digits' => '6623',
    ]);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.show', [$this->studio, $payment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.method', 'Webpay')
            ->where('payment.webpay', ['buyOrder' => 'cupo261029ABC', 'authorizationCode' => '1213', 'cardLastDigits' => '6623', 'failureReason' => null])
            ->where('canVoid', false)
            ->where('voidDenied', 'Se pagó con Webpay: solo la dueña puede anularlo.'));
    $this->actingAs($this->camila)
        ->post(route('studios.payments.void.store', [$this->studio, $payment]), ['reason' => 'Error'])
        ->assertForbidden();

    $payment->forceFill(['webpay_token' => 'tok-123'])->save();
    Http::fake([
        '*/transactions/tok-123/refunds' => Http::response(['type' => 'NULLIFIED', 'response_code' => 0]),
        '*/transactions/tok-123' => Http::response(webpayTransaction($payment)),
    ]);

    $this->actingAs($this->carla)
        ->post(route('studios.payments.void.store', [$this->studio, $payment]), ['reason' => 'Era otro plan'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Anulamos el pago y su plan.']);
    $this->actingAs($this->carla)
        ->get(route('studios.payments.show', [$this->studio, $payment]))
        ->assertInertia(fn (Assert $page) => $page->where('payment.voided.refundedAt', 'jueves 29 de octubre, 16:00'));
});

it('keeps payments for the front desk and the owner, and each studio its own', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->get(route('studios.payments.index', $this->studio))
        ->assertForbidden();
})->with([StudioRole::Instructor, StudioRole::Student]);

it('answers 404 for a payment of another studio', function () {
    $elsewhere = Payment::factory()->create();

    $this->actingAs($this->carla)
        ->get(route('studios.payments.show', [$this->studio, $elsewhere]))
        ->assertNotFound();
    $this->actingAs($this->carla)
        ->post(route('studios.payments.void.store', [$this->studio, $elsewhere]), ['reason' => 'Error'])
        ->assertNotFound();

    expect($elsewhere->refresh()->status)->toBe(PaymentStatus::Approved);
});

/**
 * A receipt Javiera sent from the app, waiting to be reviewed.
 */
function receiptToReview(Studio $studio): Payment
{
    Storage::fake();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $plan = $studio->plans()->where('name', '4 clases al mes')->first()
        ?? Plan::factory()->for($studio)->create(['name' => '4 clases al mes', 'price' => 28000]);
    $studio->update(['transfer_details' => 'Banco del Ejemplo']);

    return app(SubmitTransferReceipt::class)->handle($plan, $javiera, UploadedFile::fake()->image('comprobante.png'));
}

it('puts the receipts to review first, and shows each one with its image', function () {
    $payment = receiptToReview($this->studio);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'por-validar')
            ->where('toReview', 1)
            ->where('payments.data.0.planName', '4 clases al mes')
            ->has('payments.data', 1));
    $this->actingAs($this->camila)
        ->get(route('studios.payments.show', [$this->studio, $payment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canReview', true)
            ->where('canVoid', false)
            ->where('payment.receipt.isPdf', false)
            ->where('payment.receipt.url', route('studios.payments.receipt', [$this->studio, $payment], absolute: false)));
});

it('approves a receipt into her plan, or rejects it saying why', function () {
    $payment = receiptToReview($this->studio);
    $other = receiptToReview($this->studio);

    $this->actingAs($this->camila)
        ->post(route('studios.payments.approve', [$this->studio, $payment]))
        ->assertRedirect(route('studios.payments.show', [$this->studio, $payment]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Aprobado. Su plan parte hoy.']);
    $this->actingAs($this->camila)
        ->post(route('studios.payments.reject', [$this->studio, $other]), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Escribe por qué lo rechazas: ella lo va a leer.']);
    $this->actingAs($this->carla)
        ->post(route('studios.payments.reject', [$this->studio, $other]), ['reason' => 'No llegó la transferencia'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rechazado. Ella verá por qué y podrá enviar otro.']);
    $this->actingAs($this->carla)
        ->post(route('studios.payments.approve', [$this->studio, $other]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "{$this->carla->name} ya lo revisó."]);

    $this->actingAs($this->camila)
        ->get(route('studios.payments.show', [$this->studio, $payment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canVoid', false)
            ->where('voidDenied', 'Llegó desde la app: solo la dueña puede anularlo.'));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Approved)
        ->and($payment->planPurchase?->user_id)->toBe($payment->user_id)
        ->and($other->refresh()->status)->toBe(PaymentStatus::Rejected);
});

it('shows a receipt only to whoever reviews payments and to the student who sent it', function () {
    $payment = receiptToReview($this->studio);
    $route = route('studios.payments.receipt', [$this->studio, $payment]);

    $this->actingAs($this->camila)->get($route)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($payment->user)->get($route)->assertOk();
    $this->actingAs($this->rosa)->get($route)->assertNotFound();
    $this->actingAs(User::factory()->memberOf($this->studio, StudioRole::Instructor)->create())->get($route)->assertNotFound();
    $this->actingAs($this->carla)->get(route('studios.payments.receipt', [Studio::factory()->create(), $payment]))->assertNotFound();
});

it('keeps reviewing for the front desk and the owner', function () {
    $payment = receiptToReview($this->studio);

    $this->actingAs($payment->user)
        ->post(route('studios.payments.approve', [$this->studio, $payment]))
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentStatus::ToReview);
});
