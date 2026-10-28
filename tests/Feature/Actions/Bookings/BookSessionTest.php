<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

function bookPlace(ClassSession $session, User $person, BookingKind $kind = BookingKind::Student): Booking
{
    return app(BookSession::class)->handle($session, $person, $kind, $person);
}

/**
 * A session with an instructor of a studio with the default rules: book
 * until 60 minutes before, 14 days ahead.
 *
 * @param  array<string, mixed>  $attributes
 */
function bookableSession(array $attributes = []): ClassSession
{
    return ClassSession::factory()->create(['instructor_id' => User::factory(), ...$attributes]);
}

it('takes a spot and counts it', function () {
    $session = bookableSession(['capacity' => 10]);
    $javiera = User::factory()->create();

    $booking = bookPlace($session, $javiera);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->kind)->toBe(BookingKind::Student)
        ->and($booking->user_id)->toBe($javiera->id)
        ->and($session->fresh()->booked_count)->toBe(1);
});

it('says the class is full when the last spot is gone', function () {
    $session = bookableSession(['capacity' => 1]);
    bookPlace($session, User::factory()->create());

    expect(fn () => bookPlace($session, User::factory()->create()))->toThrow(BookingRejected::class, 'Esta clase está llena.');
    expect($session->bookings()->count())->toBe(1)
        ->and($session->fresh()->booked_count)->toBe(1);
});

it('lets someone of the team take a spot', function () {
    $session = bookableSession(['capacity' => 10]);

    $booking = bookPlace($session, User::factory()->create(), BookingKind::Staff);

    expect($booking->kind)->toBe(BookingKind::Staff)
        ->and($session->fresh()->booked_count)->toBe(1);
});

it('refuses a second booking of the same person, and allows one after cancelling', function () {
    $session = bookableSession();
    $javiera = User::factory()->create(['name' => 'Javiera Morales']);
    $booking = bookPlace($session, $javiera);

    expect(fn () => bookPlace($session, $javiera))->toThrow(BookingRejected::class, 'Javiera Morales ya tiene esta clase reservada.');

    app(CancelBooking::class)->handle($booking, $javiera);

    expect(bookPlace($session, $javiera)->status)->toBe(BookingStatus::Confirmed)
        ->and($session->fresh()->booked_count)->toBe(1);
});

it('refuses a class that is cancelled or has no instructor', function (array $attributes, string $message) {
    $session = bookableSession($attributes);

    expect(fn () => bookPlace($session, User::factory()->create()))->toThrow(BookingRejected::class, $message);
    expect($session->fresh()->booked_count)->toBe(0);
})->with([
    'cancelada' => [['status' => ClassSessionStatus::Cancelled], 'Esta clase se canceló.'],
    'sin instructor' => [['instructor_id' => null], 'Esta clase todavía no tiene instructor, así que no se puede reservar.'],
]);

it('closes bookings at the studio cutoff', function () {
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    $session = bookableSession(['studio_id' => Studio::factory()->create(['booking_cutoff_minutes' => 60]), 'starts_at' => $startsAt, 'ends_at' => $startsAt->addMinutes(50)]);

    $this->travelTo($startsAt->subMinutes(61));
    expect(bookPlace($session, User::factory()->create())->exists)->toBeTrue();

    $this->travelTo($startsAt->subMinutes(60));
    expect(fn () => bookPlace($session, User::factory()->create()))->toThrow(BookingRejected::class, 'Las reservas de esta clase ya cerraron.');
});

it('opens bookings only within the studio horizon, counting days in Santiago', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 23:30', 'America/Santiago'));
    $studio = Studio::factory()->create(['booking_horizon_days' => 14]);
    $lastDay = CarbonImmutable::parse('2026-11-15 19:00', 'America/Santiago')->utc();
    $tooFar = CarbonImmutable::parse('2026-11-16 07:00', 'America/Santiago')->utc();

    expect(bookPlace(bookableSession(['studio_id' => $studio, 'starts_at' => $lastDay, 'ends_at' => $lastDay->addMinutes(50)]), User::factory()->create())->exists)->toBeTrue();
    expect(fn () => bookPlace(bookableSession(['studio_id' => $studio, 'starts_at' => $tooFar, 'ends_at' => $tooFar->addMinutes(50)]), User::factory()->create()))
        ->toThrow(BookingRejected::class, 'Esta clase todavía no se puede reservar: las reservas abren 14 días antes.');
});

it('does not let the instructor take a spot in her own class', function () {
    $fernanda = User::factory()->create(['name' => 'Fernanda Díaz']);
    $session = bookableSession(['instructor_id' => $fernanda->id]);

    expect(fn () => bookPlace($session, $fernanda, BookingKind::Staff))
        ->toThrow(BookingRejected::class, 'Fernanda Díaz da esta clase, así que no puede ocupar un cupo en ella.');
});
