<?php

use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\Payments\VoidPayment;
use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StudioRole;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
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
