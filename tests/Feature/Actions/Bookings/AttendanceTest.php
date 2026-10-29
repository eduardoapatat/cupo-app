<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\CancelSession;
use App\Actions\Bookings\MarkAttendance;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A booking, through the action, of a class on Tuesday, November 3, at
 * 19:00. It is booked on Monday.
 */
function bookingForAttendance(): Booking
{
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    test()->travelTo($startsAt->subDay());
    $session = ClassSession::factory()->create([
        'instructor_id' => User::factory(),
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
    $javiera = User::factory()->create();

    return bookWithPlan($session, $javiera);
}

it('marks who came from the start of the class, and undoes it', function () {
    $booking = bookingForAttendance();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago'));

    expect(app(MarkAttendance::class)->handle($booking, true)->status)->toBe(BookingStatus::Attended)
        ->and(app(MarkAttendance::class)->handle($booking, false)->status)->toBe(BookingStatus::Confirmed);
});

it('keeps the attendance open until the end of the next day', function (string $now, bool $isOpen) {
    $booking = bookingForAttendance();
    $this->travelTo(CarbonImmutable::parse($now, 'America/Santiago'));

    expect($booking->classSession->attendanceIsOpen())->toBe($isOpen);
})->with([
    'before it starts' => ['2026-11-03 18:59', false],
    'the next day at 23:59' => ['2026-11-04 23:59', true],
    'two days later' => ['2026-11-05 00:00', false],
]);

it('does not mark before the class starts or after the attendance closes', function (string $now) {
    $booking = bookingForAttendance();
    $this->travelTo(CarbonImmutable::parse($now, 'America/Santiago'));

    expect(fn () => app(MarkAttendance::class)->handle($booking, true))
        ->toThrow(BookingRejected::class, 'La asistencia de esta clase no se puede marcar ahora.');
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
})->with(['2026-11-03 18:59', '2026-11-05 00:00']);

it('lets a closed attendance be corrected only when asked to, back to a no-show', function () {
    $booking = bookingForAttendance();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 00:15', 'America/Santiago'));
    $this->artisan('attendance:close');

    expect(fn () => app(MarkAttendance::class)->handle($booking->fresh(), true))
        ->toThrow(BookingRejected::class, 'La asistencia de esta clase no se puede marcar ahora.');

    $this->travelTo(CarbonImmutable::parse('2026-12-20 10:00', 'America/Santiago'));

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow)
        ->and(app(MarkAttendance::class)->handle($booking->fresh(), true, afterClosing: true)->status)->toBe(BookingStatus::Attended)
        ->and(app(MarkAttendance::class)->handle($booking->fresh(), false, afterClosing: true)->status)->toBe(BookingStatus::NoShow);
});

it('does not let the owner mark a class before it starts', function () {
    $booking = bookingForAttendance();

    expect(fn () => app(MarkAttendance::class)->handle($booking, true, afterClosing: true))
        ->toThrow(BookingRejected::class, 'La asistencia de esta clase no se puede marcar ahora.');
});

it('does not mark a cancelled booking nor a cancelled class', function () {
    $booking = bookingForAttendance();
    $cancelled = Booking::factory()->for($booking->classSession)->cancelled()->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 19:30', 'America/Santiago'));

    expect(fn () => app(MarkAttendance::class)->handle($cancelled, true))
        ->toThrow(BookingRejected::class, 'Esta reserva ya no está vigente.');

    $other = bookingForAttendance();
    app(CancelSession::class)->handle($other->classSession, User::factory()->create(), 'Feriado');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 19:30', 'America/Santiago'));

    expect(fn () => app(MarkAttendance::class)->handle($other->fresh(), true))
        ->toThrow(BookingRejected::class, 'La asistencia de esta clase no se puede marcar ahora.');
});

it('closes the attendance of the classes from before yesterday: whoever nobody marked missed it', function () {
    $booking = bookingForAttendance();
    $attended = Booking::factory()->for($booking->classSession)->create(['status' => BookingStatus::Attended]);
    $cancelled = Booking::factory()->for($booking->classSession)->cancelled()->create();
    $yesterday = Booking::factory()->for(ClassSession::factory()->startingAt(CarbonImmutable::parse('2026-11-04 19:00', 'America/Santiago')->utc()))->create();

    $this->travelTo(CarbonImmutable::parse('2026-11-04 23:59', 'America/Santiago'));
    $this->artisan('attendance:close')->assertSuccessful();
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);

    $this->travelTo(CarbonImmutable::parse('2026-11-05 00:15', 'America/Santiago'));
    $this->artisan('attendance:close')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow)
        ->and($attended->fresh()->status)->toBe(BookingStatus::Attended)
        ->and($cancelled->fresh()->status)->toBe(BookingStatus::CancelledOnTime)
        ->and($yesterday->fresh()->status)->toBe(BookingStatus::Confirmed);
});
