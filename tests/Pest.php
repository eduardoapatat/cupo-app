<?php

use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Tests that run work in other processes need committed data, so they
// truncate the tables instead of wrapping each test in a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Expect the write to fail with the given SQLSTATE, such as 23505 for a
 * unique index or 23514 for a CHECK. It runs in a savepoint, so the test's
 * transaction survives the error and the next write can run.
 */
function expectSqlState(Closure $write, string $state): void
{
    expect(fn () => DB::transaction($write))->toThrow(function (QueryException $exception) use ($state) {
        expect($exception->getCode())->toBe($state);
    });
}

/**
 * Give the student a plan that pays for any class of the studio: unlimited,
 * without a daily limit and valid from a year ago to a year ahead. For tests
 * about bookings, not about plans.
 *
 * @param  array<string, mixed>  $attributes
 */
function givePlan(User $student, Studio|ClassSession $at, array $attributes = []): PlanPurchase
{
    return PlanPurchase::factory()->unlimited()->create([
        'studio_id' => $at instanceof ClassSession ? $at->studio_id : $at->id,
        'user_id' => $student->id,
        'daily_limit' => null,
        'starts_on' => now()->subYear()->toDateString(),
        'expires_at' => now()->addYear(),
        ...$attributes,
    ]);
}

/**
 * Book the class for the student, giving her a plan first if she has none
 * in that studio. For tests about bookings, not about plans.
 */
function bookWithPlan(ClassSession $session, User $student, ?User $by = null): Booking
{
    if (! $student->planPurchases()->where('studio_id', $session->studio_id)->exists()) {
        givePlan($student, $session);
    }

    return app(BookSession::class)->handle($session, $student, BookingKind::Student, $by ?? $student);
}
