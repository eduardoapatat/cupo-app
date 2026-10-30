<?php

use App\Actions\CashRegisters\CashRegisterRejected;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashRegister;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency, as in BookSessionConcurrencyTest: the front desk sells
 * a plan in cash at the same moment her register is being closed. The sale
 * either gets in before the close and is counted, or comes after and is
 * rejected; it never lands in a closed register uncounted.
 */
/**
 * A cash sale into the register, to run in its own process. Built outside
 * the test class, so the closure carries no class the process cannot load.
 */
function cashSaleInAnotherProcess(int $planId, int $studentId, int $registerId, float $startAt): Closure
{
    return static function () use ($planId, $studentId, $registerId, $startAt): string {
        time_sleep_until($startAt);

        try {
            app(SellPlan::class)->handle(Plan::findOrFail($planId), User::findOrFail($studentId), CashRegister::findOrFail($registerId), PaymentMethod::Cash);

            return 'sold';
        } catch (CashRegisterRejected) {
            return 'rejected';
        }
    };
}

/**
 * Closing the register counting exactly what it started with, in its own
 * process.
 */
function registerCloseInAnotherProcess(int $registerId, float $startAt): Closure
{
    return static function () use ($registerId, $startAt): string {
        time_sleep_until($startAt);
        $register = CashRegister::findOrFail($registerId);
        app(CloseCashRegister::class)->handle($register, $register->opening_cash, 'Contado al cerrar');

        return 'closed';
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('never leaves a sale uncounted in a closed register', function () {
    $studio = Studio::factory()->create();
    $plan = Plan::factory()->for($studio)->create(['price' => 45000]);
    $student = User::factory()->create();
    // Several registers, so the sale and the close cross in some of them.
    $registers = CashRegister::factory()->for($studio)->count(8)->create();
    $startAt = microtime(true) + 2;

    $tasks = $registers->flatMap(fn (CashRegister $register): array => [
        cashSaleInAnotherProcess($plan->id, $student->id, $register->id, $startAt),
        registerCloseInAnotherProcess($register->id, $startAt),
    ])->all();
    $results = Concurrency::driver('process')->run($tasks);

    expect(array_count_values($results)['closed'])->toBe(8);

    foreach ($registers as $register) {
        $register->refresh();
        $paid = (int) $register->payments()->where('status', PaymentStatus::Approved)->sum('amount');

        expect($register->isClosed())->toBeTrue()
            ->and($register->cash_total)->toBe($paid)
            ->and($register->expected_cash)->toBe($register->opening_cash + $paid);
    }
});
