<?php

use App\Actions\Plans\SellPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency, as in PaymentReviewConcurrencyTest: the front desk and
 * the owner sell a plan to the same student at the same moment. One plan
 * starts today and the other waits its turn; never two at once.
 */
/**
 * Creating her plan, to run in its own process. Built outside the test
 * class, so the closure carries no class the process cannot load.
 */
function newPlanInAnotherProcess(int $planId, int $studentId, float $startAt): Closure
{
    return static function () use ($planId, $studentId, $startAt): string {
        time_sleep_until($startAt);

        $plan = Plan::findOrFail($planId);

        return app(SellPlan::class)->newPurchase($plan, User::findOrFail($studentId), null, $plan->price)->starts_on->toDateString();
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('queues the second of two plans sold to her at once', function () {
    $plan = Plan::factory()->create(['credits' => 8, 'validity_days' => 30]);
    $student = User::factory()->create();
    $startAt = microtime(true) + 2;

    $starts = Concurrency::driver('process')->run([
        newPlanInAnotherProcess($plan->id, $student->id, $startAt),
        newPlanInAnotherProcess($plan->id, $student->id, $startAt),
    ]);
    sort($starts);

    $today = now(config()->string('studio.timezone'))->startOfDay();
    expect($starts)->toBe([$today->toDateString(), $today->addDays(30)->toDateString()]);
});
