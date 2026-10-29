<?php

use App\Models\Payment;
use App\Models\PlanPurchase;
use Illuminate\Support\Facades\DB;

it('never leaves fewer than 0 classes or more than were bought', function (array $values) {
    $purchase = PlanPurchase::factory()->create();

    expectSqlState(fn () => DB::table('plan_purchases')->where('id', $purchase->id)->update($values), '23514');
})->with([
    'below zero' => [['credits_left' => -1]],
    'above the plan' => [['credits_left' => 9]],
    'a limited plan without a count' => [['credits_left' => null]],
    'an unlimited plan with a count' => [['credits' => null]],
    'a negative price' => [['price' => -1]],
    'ending before it starts' => [['expires_at' => '2020-01-01 00:00:00+00']],
]);

it('rejects a negative payment', function () {
    $payment = Payment::factory()->create();

    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update(['amount' => -1]), '23514');
});
