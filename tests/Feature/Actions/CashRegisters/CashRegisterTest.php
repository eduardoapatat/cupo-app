<?php

use App\Actions\CashRegisters\CashRegisterRejected;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\CashRegisters\OpenCashRegister;
use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Models\CashRegister;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Thursday, October 29, in the morning in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 09:00', 'America/Santiago'));
    $this->studio = Studio::factory()->create();
    $this->camila = User::factory()->create();
});

it('opens today\'s register with the cash she starts with', function () {
    $register = app(OpenCashRegister::class)->handle($this->studio, $this->camila, 20000);

    expect($register)
        ->opening_cash->toBe(20000)
        ->user_id->toBe($this->camila->id)
        ->and($register->business_date->toDateString())->toBe('2026-10-29')
        ->and($register->isClosed())->toBeFalse();
});

it('opens one register a day, and not while one of another day is open', function () {
    CashRegister::factory()->for($this->studio)->for($this->camila)->onDaysAgo(1)->create();

    expect(fn () => app(OpenCashRegister::class)->handle($this->studio, $this->camila, 0))
        ->toThrow(CashRegisterRejected::class, 'Cierra primero tu caja del miércoles 28 de octubre.');

    $this->camila->cashRegisters()->delete();
    $today = app(OpenCashRegister::class)->handle($this->studio, $this->camila, 0);

    expect(fn () => app(OpenCashRegister::class)->handle($this->studio, $this->camila, 0))
        ->toThrow(CashRegisterRejected::class, 'Tu caja ya está abierta.');

    app(CloseCashRegister::class)->handle($today, 0);

    expect(fn () => app(OpenCashRegister::class)->handle($this->studio, $this->camila, 0))
        ->toThrow(CashRegisterRejected::class, 'Ya cerraste tu caja de hoy.')
        ->and(app(OpenCashRegister::class)->handle(Studio::factory()->create(), $this->camila, 0)->exists)->toBeTrue();
});

it('closes with the totals per method and the expected cash, frozen from then on', function () {
    $register = app(OpenCashRegister::class)->handle($this->studio, $this->camila, 20000);
    $plan = Plan::factory()->for($this->studio)->create(['price' => 45000]);
    app(SellPlan::class)->handle($plan, User::factory()->create(), $register, PaymentMethod::Cash);
    app(SellPlan::class)->handle($plan, User::factory()->create(), $register, PaymentMethod::Card);
    app(SellPlan::class)->handle($plan, User::factory()->create(), $register, PaymentMethod::Transfer, 30000, 'Amiga');

    $closed = app(CloseCashRegister::class)->handle($register, 63000, 'Di vuelto de más');

    expect($closed->refresh())
        ->expected_cash->toBe(65000)
        ->counted_cash->toBe(63000)
        ->closing_note->toBe('Di vuelto de más')
        ->and($closed->totals())->toBe(['cash' => 45000, 'card' => 45000, 'transfer' => 30000])
        ->and($closed->difference())->toBe(-2000);
});

it('asks why when the cash does not match, and closes only once', function () {
    $register = app(OpenCashRegister::class)->handle($this->studio, $this->camila, 20000);

    expect(fn () => app(CloseCashRegister::class)->handle($register, 19000, '  '))
        ->toThrow(CashRegisterRejected::class, 'El efectivo no cuadra: escribe por qué.');

    app(CloseCashRegister::class)->handle($register, 20000);

    expect(fn () => app(CloseCashRegister::class)->handle($register, 20000))
        ->toThrow(CashRegisterRejected::class, 'Esta caja ya está cerrada.')
        ->and($register->refresh()->difference())->toBe(0);
});
