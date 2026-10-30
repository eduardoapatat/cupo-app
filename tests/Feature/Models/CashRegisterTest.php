<?php

use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @return array<string, mixed>
 */
function closingValues(int $expected = 20000, int $counted = 20000): array
{
    return [
        'closed_at' => now(),
        'expected_cash' => $expected,
        'counted_cash' => $counted,
        'cash_total' => 0,
        'card_total' => 0,
        'transfer_total' => 0,
    ];
}

it('keeps one register per person and day, and one open at a time', function () {
    $register = CashRegister::factory()->create();
    $samePerson = ['studio_id' => $register->studio_id, 'user_id' => $register->user_id];

    expectSqlState(fn () => CashRegister::factory()->create($samePerson), '23505');
    expectSqlState(fn () => CashRegister::factory()->onDaysAgo(1)->create($samePerson), '23505');
});

it('closes a register only with its count and totals, and a note when it does not match', function (array $values) {
    $register = CashRegister::factory()->create();

    expectSqlState(fn () => DB::table('cash_registers')->where('id', $register->id)->update($values), '23514');
})->with([
    'without the count' => [[...closingValues(), 'counted_cash' => null]],
    'without a total' => [[...closingValues(), 'card_total' => null]],
    'with a count but open' => [['counted_cash' => 20000]],
    'short without a note' => [closingValues(counted: 18000)],
    'a negative opening' => [['opening_cash' => -1]],
]);

it('never changes a closed register, but lets the account of who ran it go', function () {
    $register = CashRegister::factory()->create();
    DB::table('cash_registers')->where('id', $register->id)->update([...closingValues(counted: 18000), 'closing_note' => 'Faltó sencillo']);

    expectSqlState(fn () => DB::table('cash_registers')->where('id', $register->id)->update(['counted_cash' => 20000]), '23514');
    expectSqlState(fn () => DB::table('cash_registers')->where('id', $register->id)->update(['closed_at' => null]), '23514');

    User::findOrFail($register->user_id)->delete();

    expect($register->fresh())->user_id->toBeNull()->counted_cash->toBe(18000);
});

it('takes no payment into a closed register and lets none out', function () {
    $open = CashRegister::factory()->create();
    $payment = Payment::factory()->create(['studio_id' => $open->studio_id, 'cash_register_id' => $open->id]);
    $closed = CashRegister::factory()->onDaysAgo(1)->closed()->create(['studio_id' => $open->studio_id]);

    expectSqlState(fn () => Payment::factory()->create(['cash_register_id' => $closed->id]), '23514');
    expectSqlState(fn () => $payment->update(['cash_register_id' => $closed->id]), '23514');

    DB::table('cash_registers')->where('id', $open->id)->update(closingValues(65000, 65000));

    expectSqlState(fn () => $payment->update(['cash_register_id' => null]), '23514');
});
