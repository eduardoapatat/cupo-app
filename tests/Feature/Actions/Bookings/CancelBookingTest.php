<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A booking, through the action, of a class on Tuesday at 19:00 in a
 * studio with a 6-hour cancellation window. It is booked on Monday.
 */
function bookingOnTuesdayAtSeven(): Booking
{
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    test()->travelTo($startsAt->subDay());
    $session = ClassSession::factory()->create([
        'studio_id' => Studio::factory()->create(['cancellation_window_hours' => 6]),
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
    $javiera = User::factory()->create();

    return app(BookSession::class)->handle($session, $javiera, BookingKind::Student, $javiera);
}

function cancelPlace(Booking $booking, bool $returnClass = false, ?string $reason = null): Booking
{
    return app(CancelBooking::class)->handle($booking, $booking->user, $returnClass, $reason);
}

it('cancels on time up to the window, and frees the spot', function () {
    $booking = bookingOnTuesdayAtSeven();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 13:00', 'America/Santiago'));

    $cancelled = cancelPlace($booking);

    expect($cancelled->status)->toBe(BookingStatus::CancelledOnTime)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancelled_by_id)->toBe($booking->user_id)
        ->and($booking->classSession->fresh()->booked_count)->toBe(0);
});

it('cancels late after the window, and still frees the spot', function () {
    $booking = bookingOnTuesdayAtSeven();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 13:01', 'America/Santiago'));

    expect(cancelPlace($booking)->status)->toBe(BookingStatus::CancelledLate)
        ->and($booking->classSession->fresh()->booked_count)->toBe(0);
});

it('returns the class when asked, with the reason', function () {
    $booking = bookingOnTuesdayAtSeven();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 18:00', 'America/Santiago'));

    $cancelled = cancelPlace($booking, returnClass: true, reason: 'Se enfermó');

    expect($cancelled->status)->toBe(BookingStatus::CancelledOnTime)
        ->and($cancelled->cancellation_reason)->toBe('Se enfermó');
});

it('frees a single spot when the same booking is cancelled twice', function () {
    $booking = bookingOnTuesdayAtSeven();
    $other = app(BookSession::class)->handle($booking->classSession, $user = User::factory()->create(), BookingKind::Student, $user);
    $stale = Booking::find($booking->id);

    cancelPlace($booking);

    expect(fn () => cancelPlace($stale))->toThrow(BookingRejected::class, 'Esta reserva ya no está vigente.');
    expect($other->classSession->fresh()->booked_count)->toBe(1);
});

it('does not cancel once the class started', function () {
    $booking = bookingOnTuesdayAtSeven();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago'));

    expect(fn () => cancelPlace($booking))->toThrow(BookingRejected::class, 'La clase ya empezó, así que la reserva ya no se puede cancelar.');
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});
