<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Models\ClassSession;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency, as in BookSessionConcurrencyTest: one student books two
 * classes from two phones at the same moment, and both bookings compete for
 * the same plan.
 */
/**
 * One booking, to run in its own process. Built outside the test class, so
 * the closure carries no class the process cannot load.
 */
function planBookingInAnotherProcess(int $sessionId, int $studentId, float $startAt): Closure
{
    return static function () use ($sessionId, $studentId, $startAt): string {
        time_sleep_until($startAt);
        $student = User::findOrFail($studentId);

        try {
            app(BookSession::class)->handle(ClassSession::findOrFail($sessionId), $student, BookingKind::Student, $student);

            return 'booked';
        } catch (BookingRejected) {
            return 'rejected';
        }
    };
}

/**
 * Two classes of the same studio on the same day, three hours apart, and
 * the student's plan.
 *
 * @param  array<string, mixed>  $plan
 * @return array{0: list<int>, 1: PlanPurchase}
 */
function twoClassesAndAPlan(array $plan): array
{
    $studio = Studio::factory()->create();
    $startsAt = now()->addDays(2)->startOfHour();
    $sessionIds = collect([0, 3])->map(fn (int $hours): int => ClassSession::factory()->for($studio)->create([
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt->addHours($hours),
        'ends_at' => $startsAt->addHours($hours)->addMinutes(50),
    ])->id)->all();
    $purchase = PlanPurchase::factory()->for($studio)->create($plan);

    return [$sessionIds, $purchase];
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('never takes the last class of a plan twice', function () {
    [$sessionIds, $purchase] = twoClassesAndAPlan(['credits' => 8, 'credits_left' => 1]);
    $startAt = microtime(true) + 2;

    $results = Concurrency::driver('process')->run(array_map(
        fn (int $sessionId): Closure => planBookingInAnotherProcess($sessionId, $purchase->user_id, $startAt),
        $sessionIds,
    ));
    sort($results);

    expect($results)->toBe(['booked', 'rejected'])
        ->and($purchase->fresh()->credits_left)->toBe(0)
        ->and($purchase->bookings()->count())->toBe(1);
});

it('never goes past the daily limit of an unlimited plan', function () {
    [$sessionIds, $purchase] = twoClassesAndAPlan(['credits' => null, 'credits_left' => null, 'daily_limit' => 1]);
    $startAt = microtime(true) + 2;

    $results = Concurrency::driver('process')->run(array_map(
        fn (int $sessionId): Closure => planBookingInAnotherProcess($sessionId, $purchase->user_id, $startAt),
        $sessionIds,
    ));
    sort($results);

    expect($results)->toBe(['booked', 'rejected'])
        ->and($purchase->bookings()->count())->toBe(1);
});
