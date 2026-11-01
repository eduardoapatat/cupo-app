<?php

use App\Models\Payment;
use App\Models\Plan;
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

it('voids a payment only with its date and reason', function (array $values) {
    $payment = Payment::factory()->create();

    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update($values), '23514');
})->with([
    'voided without a date' => [['status' => 'voided', 'status_reason' => 'Error']],
    'voided without a reason' => [['status' => 'voided', 'voided_at' => now()]],
    'a date while approved' => [['voided_at' => now()]],
    'an unknown status' => [['status' => 'refunded']],
]);

it('dates a payment exactly when it was approved', function (array $values) {
    $payment = Payment::factory()->create();

    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update($values), '23514');
})->with([
    'approved without a date' => [['paid_at' => null]],
    'to review with a date' => [['status' => 'to_review', 'plan_purchase_id' => null, 'plan_id' => null, 'receipt_path' => 'receipts/1/a.png']],
]);

it('keeps a receipt to review without a purchase, and one at a time per student', function () {
    $payment = Payment::factory()->create();
    $toReview = ['status' => 'to_review', 'plan_purchase_id' => null, 'plan_id' => Plan::factory()->create()->id, 'receipt_path' => 'receipts/1/a.png', 'paid_at' => null];

    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update(['plan_purchase_id' => null]), '23514');
    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update([...$toReview, 'receipt_path' => null]), '23514');
    expectSqlState(fn () => DB::table('payments')->where('id', $payment->id)->update(['status' => 'rejected', 'plan_purchase_id' => null]), '23514');

    DB::table('payments')->where('id', $payment->id)->update($toReview);
    $second = Payment::factory()->create(['studio_id' => $payment->studio_id, 'user_id' => $payment->user_id]);

    expectSqlState(fn () => DB::table('payments')->where('id', $second->id)->update($toReview), '23505');
});
