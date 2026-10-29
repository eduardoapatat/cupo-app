<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;

/**
 * Real concurrency: each booking runs in its own PHP process with its own
 * database connection, so they race for the last spots the way two phones
 * would. The data must be committed for the processes to see it, hence
 * DatabaseTruncation instead of RefreshDatabase (see tests/Pest.php).
 */
/**
 * One student's booking, to run in its own process. It is built outside
 * the test class, so the closure carries no class the process cannot load.
 */
function bookingInAnotherProcess(int $sessionId, int $studentId, float $startAt): Closure
{
    return static function () use ($sessionId, $studentId, $startAt): string {
        time_sleep_until($startAt);
        $student = User::findOrFail($studentId);

        try {
            app(BookSession::class)->handle(ClassSession::findOrFail($sessionId), $student, BookingKind::Student, $student);

            return 'booked';
        } catch (BookingRejected $rejected) {
            return $rejected->getMessage();
        }
    };
}

afterEach(function () {
    // Leave the testing database empty for the suites that run after this one.
    $this->truncateTablesForAllConnections();
});

it('never books more people than the capacity, even all at once', function () {
    $session = ClassSession::factory()->create(['capacity' => 10, 'instructor_id' => User::factory()]);
    $students = User::factory()->count(15)->create()->each(fn (User $student) => givePlan($student, $session));
    $studentIds = $students->modelKeys();
    $sessionId = $session->id;

    // Every process waits for the same moment, after booting, to book.
    $startAt = microtime(true) + 2;

    $results = Concurrency::driver('process')->run(array_map(
        fn (int $studentId): Closure => bookingInAnotherProcess($sessionId, $studentId, $startAt),
        $studentIds,
    ));

    $outcomes = array_count_values($results);
    ksort($outcomes);

    expect($outcomes)->toBe(['Esta clase está llena.' => 5, 'booked' => 10])
        ->and($session->bookings()->count())->toBe(10)
        ->and($session->fresh()->booked_count)->toBe(10);
});
