<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CancelSession;
use App\Actions\Bookings\MarkNoShow;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\StudioRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
});

/**
 * The student's booking, through the action, of a class at the given local time.
 */
function bookingOf(User $student, Studio $studio, string $localTime): Booking
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();
    $session = ClassSession::factory()->for($studio)->create([
        'class_type_id' => ClassType::factory()->for($studio),
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);

    return app(BookSession::class)->handle($session, $student, BookingKind::Student, $student);
}

it('shows her upcoming classes in order, and her history without them', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $later = bookingOf($javiera, $studio, '2026-11-05 19:00');
    $sooner = bookingOf($javiera, $studio, '2026-11-03 19:00');
    $cancelledByStudio = bookingOf($javiera, $studio, '2026-11-04 19:00');
    app(CancelSession::class)->handle($cancelledByStudio->classSession, User::factory()->create(), 'Feriado');
    bookingOf(User::factory()->memberOf($studio, StudioRole::Student)->create(), $studio, '2026-11-06 19:00');
    bookingOf($javiera, Studio::factory()->create(), '2026-11-06 07:00');

    $this->actingAs($javiera)
        ->get(route('studios.my-bookings.index', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('studios/my-bookings')
            ->where('upcoming', fn ($upcoming) => collect($upcoming)->pluck('id')->all() === [$sooner->id, $later->id])
            ->where('upcoming.0.date', 'martes 3 de noviembre')
            ->where('upcoming.0.freeCancellationUntil', 'las 13:00')
            ->where('upcoming.0.cancelsOnTime', true)
            ->where('history.data', fn ($history) => collect($history)->map(fn ($booking) => [$booking['id'], $booking['status']])->all() === [
                [$cancelledByStudio->id, 'El estudio canceló la clase'],
            ]));
});

it('shows a past class nobody marked as attended, and a marked one as missed', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $attended = bookingOf($javiera, $studio, '2026-11-02 19:00');
    $missed = bookingOf($javiera, $studio, '2026-11-02 20:00');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 20:30', 'America/Santiago'));
    app(MarkNoShow::class)->handle($missed, true);

    $this->actingAs($javiera)
        ->get(route('studios.my-bookings.index', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('history.data', fn ($history) => collect($history)->map(fn ($booking) => [$booking['id'], $booking['status']])->all() === [
                [$missed->id, 'Faltó'],
                [$attended->id, 'Asistió'],
            ]));
});

it('keeps her own late cancellation in the history when the studio cancels the class afterwards', function () {
    $studio = Studio::factory()->create(['cancellation_window_hours' => 6]);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $booking = bookingOf($javiera, $studio, '2026-11-02 16:00');
    app(CancelBooking::class)->handle($booking, $javiera);
    app(CancelSession::class)->handle($booking->classSession, User::factory()->create(), 'Corte de luz');

    $this->actingAs($javiera)
        ->get(route('studios.my-bookings.index', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('history.data.0.status', 'Cancelada tarde'));
});

it('cancels her own booking on time or late', function (string $now, BookingStatus $status, string $message) {
    $studio = Studio::factory()->create(['cancellation_window_hours' => 6]);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $booking = bookingOf($javiera, $studio, '2026-11-03 19:00');
    $this->travelTo(CarbonImmutable::parse($now, 'America/Santiago'));

    $this->actingAs($javiera)
        ->delete(route('studios.my-bookings.destroy', [$studio, $booking]))
        ->assertRedirect(route('studios.my-bookings.index', $studio))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $message]);

    expect($booking->fresh()->status)->toBe($status)
        ->and($booking->classSession->fresh()->booked_count)->toBe(0);
})->with([
    'a tiempo' => ['2026-11-03 12:00', BookingStatus::CancelledOnTime, 'Cancelaste tu clase a tiempo: no la pierdes.'],
    'tarde' => ['2026-11-03 14:00', BookingStatus::CancelledLate, 'Cancelaste tu clase. Como fue después del plazo de cancelación gratis, la clase cuenta como usada.'],
]);

it('shows the full classes she waits for, with her place or that the cutoff passed', function () {
    $studio = Studio::factory()->create(['waitlist_cutoff_hours' => 6]);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $tomorrow = bookingOf(User::factory()->create(), $studio, '2026-11-03 19:00')->classSession;
    $today = bookingOf(User::factory()->create(), $studio, '2026-11-02 17:00')->classSession;
    WaitlistEntry::factory()->for($tomorrow)->create();
    WaitlistEntry::factory()->for($tomorrow)->for($javiera)->create();
    WaitlistEntry::factory()->for($today)->for($javiera)->create();

    $this->actingAs($javiera)
        ->get(route('studios.my-bookings.index', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('waiting', fn ($waiting) => collect($waiting)->map(fn ($entry) => [$entry['startsAt'], $entry['position'], $entry['isOpen']])->all() === [
                ['17:00', 1, false],
                ['19:00', 2, true],
            ]));
});

it('hides the bookings of other students and of other studios', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $rosasBooking = bookingOf(User::factory()->memberOf($studio, StudioRole::Student)->create(), $studio, '2026-11-03 19:00');
    $elsewhere = bookingOf($javiera, Studio::factory()->create(), '2026-11-03 07:00');

    $this->actingAs($javiera)
        ->delete(route('studios.my-bookings.destroy', [$studio, $rosasBooking]))
        ->assertNotFound();
    $this->actingAs($javiera)
        ->delete(route('studios.my-bookings.destroy', [$studio, $elsewhere]))
        ->assertNotFound();

    expect($rosasBooking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('is only for students', function () {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::Owner)->create())
        ->get(route('studios.my-bookings.index', $studio))
        ->assertForbidden();
});
