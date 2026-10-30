<?php

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Models\CashRegister;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 16:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->camila = User::factory()->memberOf($this->studio, StudioRole::FrontDesk)->create(['name' => 'Camila Soto']);
    $this->carla = User::factory()->memberOf($this->studio, StudioRole::Owner)->create(['name' => 'Carla Rojas']);
});

it('asks to open the register when she has none', function () {
    $this->actingAs($this->camila)
        ->get(route('studios.cash-register.index', $this->studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/cash-register')
            ->where('register', null)
            ->where('history', [])
            ->where('team', null));
});

it('opens her register and shows what it takes in', function () {
    $this->actingAs($this->camila)
        ->post(route('studios.cash-register.store', $this->studio), ['opening_cash' => 20000])
        ->assertRedirect(route('studios.cash-register.index', $this->studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Abriste tu caja.']);
    $register = $this->camila->cashRegisters()->sole();
    $plan = Plan::factory()->for($this->studio)->create(['name' => '8 clases al mes', 'price' => 45000]);
    app(SellPlan::class)->handle($plan, User::factory()->create(['name' => 'Rosa Fuentes']), $register, PaymentMethod::Cash);

    $this->actingAs($this->camila)
        ->get(route('studios.cash-register.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.isClosed', false)
            ->where('register.isToday', true)
            ->where('register.openingCash', 20000)
            ->where('register.totals', ['cash' => 45000, 'card' => 0, 'transfer' => 0])
            ->where('register.expectedCash', 65000)
            ->where('register.payments.0.studentName', 'Rosa Fuentes')
            ->where('register.payments.0.planName', '8 clases al mes')
            ->where('register.payments.0.method', 'Efectivo'));
});

it('closes her register, asking why when the cash does not match', function () {
    $register = CashRegister::factory()->for($this->studio)->for($this->camila)->create(['opening_cash' => 20000]);
    $route = route('studios.cash-register.close', [$this->studio, $register]);

    $this->actingAs($this->camila)
        ->post($route, ['counted_cash' => 18000])
        ->assertSessionHasErrors(['closing_note' => 'El efectivo no cuadra: escribe por qué.']);
    $this->actingAs($this->camila)
        ->post($route, ['counted_cash' => 18000, 'closing_note' => 'Di vuelto de más'])
        ->assertRedirect(route('studios.cash-register.index', $this->studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cerraste la caja.']);

    expect($register->refresh()->difference())->toBe(-2000);
});

it('closes only her own register, while the owner closes anyone\'s', function () {
    $carlas = CashRegister::factory()->for($this->studio)->for($this->carla)->create();
    $camilas = CashRegister::factory()->for($this->studio)->for($this->camila)->onDaysAgo(1)->create();
    $elsewhere = CashRegister::factory()->create();

    $this->actingAs($this->camila)
        ->post(route('studios.cash-register.close', [$this->studio, $carlas]), ['counted_cash' => 20000])
        ->assertNotFound();
    $this->actingAs($this->carla)
        ->post(route('studios.cash-register.close', [$this->studio, $elsewhere]), ['counted_cash' => 20000])
        ->assertNotFound();
    $this->actingAs($this->carla)
        ->post(route('studios.cash-register.close', [$this->studio, $camilas]), ['counted_cash' => 20000])
        ->assertRedirect();

    expect($carlas->refresh()->isClosed())->toBeFalse()
        ->and($camilas->refresh()->isClosed())->toBeTrue();
});

it('shows the owner the registers of the whole team', function () {
    CashRegister::factory()->for($this->studio)->for($this->camila)->onDaysAgo(1)->closed()->create();
    CashRegister::factory()->for($this->studio)->for($this->camila)->onDaysAgo(20)->closed()->create();
    CashRegister::factory()->for($this->studio)->for($this->carla)->onDaysAgo(30)->create();

    $this->actingAs($this->carla)
        ->get(route('studios.cash-register.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('register.isToday', false)
            ->where('team', fn ($team) => collect($team)->map(fn ($register) => [$register['personName'], $register['isClosed']])->all() === [
                ['Camila Soto', true],
                ['Carla Rojas', false],
            ]));
    $this->actingAs($this->camila)
        ->get(route('studios.cash-register.index', $this->studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team', null)
            ->has('history', 2));
});

it('keeps the register for the front desk and the owner', function (StudioRole $role) {
    $this->actingAs(User::factory()->memberOf($this->studio, $role)->create())
        ->get(route('studios.cash-register.index', $this->studio))
        ->assertForbidden();
})->with([StudioRole::Instructor, StudioRole::Student]);
