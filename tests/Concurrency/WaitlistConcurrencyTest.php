<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\JoinWaitlist;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency, as in BookSessionConcurrencyTest: each cancellation
 * runs in its own process, and both free a spot at the same moment while
 * two people wait for one.
 */
/**
 * One cancellation, to run in its own process. Built outside the test
 * class, so the closure carries no class the process cannot load.
 */
function cancellationInAnotherProcess(int $bookingId, float $startAt): Closure
{
    return static function () use ($bookingId, $startAt): string {
        time_sleep_until($startAt);
        $booking = Booking::findOrFail($bookingId);

        return app(CancelBooking::class)->handle($booking, $booking->user)->promoted->name ?? 'nobody';
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('gives each freed spot to a different person waiting, never more than the capacity', function () {
    $session = ClassSession::factory()->create(['capacity' => 2, 'instructor_id' => User::factory()]);
    $bookingIds = User::factory()->count(2)->create()->map(fn (User $student): int => $session->bookings()->create([
        'user_id' => $student->id,
        'kind' => BookingKind::Student,
    ])->id)->all();
    ClassSession::query()->whereKey($session->id)->update(['booked_count' => 2]);
    foreach (['Javiera', 'Rosa'] as $name) {
        $person = User::factory()->create(['name' => $name]);
        app(JoinWaitlist::class)->handle($session->refresh(), $person, $person);
    }

    $startAt = microtime(true) + 2;

    $results = Concurrency::driver('process')->run(array_map(
        fn (int $bookingId): Closure => cancellationInAnotherProcess($bookingId, $startAt),
        $bookingIds,
    ));
    sort($results);

    expect($results)->toBe(['Javiera', 'Rosa'])
        ->and($session->fresh()->booked_count)->toBe(2)
        ->and($session->bookings()->where('status', BookingStatus::Confirmed)->count())->toBe(2)
        ->and($session->waitlistEntries()->where('status', WaitlistStatus::Promoted)->count())->toBe(2);
});
