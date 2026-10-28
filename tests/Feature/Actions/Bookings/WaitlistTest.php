<?php

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CancelSession;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Bookings\LeaveWaitlist;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;

/**
 * A full class on Tuesday at 19:00 (capacity 1, booked by Ana through the
 * action) in a studio whose waitlist closes 6 hours before. It is Monday.
 */
function fullClassOnTuesday(): ClassSession
{
    $startsAt = CarbonImmutable::parse('2026-11-03 19:00', 'America/Santiago')->utc();
    test()->travelTo($startsAt->subDay());
    $session = ClassSession::factory()->create([
        'studio_id' => Studio::factory()->create(['waitlist_cutoff_hours' => 6, 'booking_cutoff_minutes' => 60]),
        'instructor_id' => User::factory(),
        'capacity' => 1,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
    ]);
    $ana = User::factory()->create(['name' => 'Ana']);
    app(BookSession::class)->handle($session, $ana, BookingKind::Student, $ana);

    return $session->refresh();
}

function waitFor(ClassSession $session, User $person): WaitlistEntry
{
    return app(JoinWaitlist::class)->handle($session, $person, $person);
}

function firstBookingOf(ClassSession $session): Booking
{
    return $session->bookings()->current()->oldest('id')->firstOrFail();
}

it('puts people in line, first come first served', function () {
    $session = fullClassOnTuesday();
    $javiera = User::factory()->create();
    $rosa = User::factory()->create();
    $camila = User::factory()->create();

    $first = app(JoinWaitlist::class)->handle($session, $javiera, $javiera);
    $second = app(JoinWaitlist::class)->handle($session, $rosa, $camila);

    expect($first->status)->toBe(WaitlistStatus::Waiting)
        ->and($first->position())->toBe(1)
        ->and($second->position())->toBe(2)
        ->and($second->added_by_id)->toBe($camila->id);
});

it('does not let anyone wait while a spot is free', function () {
    $session = fullClassOnTuesday();
    app(CancelBooking::class)->handle(firstBookingOf($session), User::factory()->create());

    expect(fn () => waitFor($session->refresh(), User::factory()->create()))
        ->toThrow(BookingRejected::class, 'Se liberó un lugar: resérvalo.');
});

it('closes the waitlist at its cutoff', function () {
    $session = fullClassOnTuesday();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 13:00', 'America/Santiago'));

    expect(fn () => waitFor($session, User::factory()->create()))
        ->toThrow(BookingRejected::class, 'La lista de espera de esta clase ya cerró.');
});

it('does not let someone wait twice, with the class booked, or in the class she teaches', function () {
    $session = fullClassOnTuesday();
    $javiera = User::factory()->create(['name' => 'Javiera']);
    waitFor($session, $javiera);

    expect(fn () => waitFor($session, $javiera))
        ->toThrow(BookingRejected::class, 'Javiera ya está en la lista de espera.');
    expect(fn () => waitFor($session, firstBookingOf($session)->user))
        ->toThrow(BookingRejected::class, 'Ana ya tiene esta clase reservada.');
    expect(fn () => waitFor($session, $session->instructor))
        ->toThrow(BookingRejected::class, 'da esta clase');
});

it('does not let anyone wait for a cancelled class', function () {
    $session = fullClassOnTuesday();
    app(CancelSession::class)->handle($session, User::factory()->create(), 'Feriado');

    expect(fn () => waitFor($session, User::factory()->create()))
        ->toThrow(BookingRejected::class, 'Esta clase se canceló.');
});

it('gives the freed spot to the first one waiting', function () {
    $session = fullClassOnTuesday();
    $javiera = User::factory()->create(['name' => 'Javiera']);
    $camila = User::factory()->create();
    $entry = app(JoinWaitlist::class)->handle($session, $javiera, $camila);
    $rosaEntry = waitFor($session, User::factory()->create());

    $cancellation = app(CancelBooking::class)->handle(firstBookingOf($session), User::factory()->create());

    $entry->refresh();
    expect($cancellation->promoted?->is($javiera))->toBeTrue()
        ->and($entry->status)->toBe(WaitlistStatus::Promoted)
        ->and($entry->booking_id)->not->toBeNull()
        ->and(Booking::find($entry->booking_id))
        ->user_id->toBe($javiera->id)
        ->status->toBe(BookingStatus::Confirmed)
        ->booked_by_id->toBe($camila->id)
        ->and($session->fresh()->booked_count)->toBe(1)
        ->and($rosaEntry->refresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and($rosaEntry->position())->toBe(1);
});

it('leaves the freed spot open for anyone after the waitlist cutoff', function () {
    $session = fullClassOnTuesday();
    $entry = waitFor($session, User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2026-11-03 13:00', 'America/Santiago'));

    $cancellation = app(CancelBooking::class)->handle(firstBookingOf($session), User::factory()->create());

    expect($cancellation->promoted)->toBeNull()
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Waiting)
        ->and($session->fresh()->booked_count)->toBe(0);
});

it('skips someone who now teaches the class', function () {
    $session = fullClassOnTuesday();
    $diego = User::factory()->create();
    $javiera = User::factory()->create();
    $diegoEntry = waitFor($session, $diego);
    waitFor($session, $javiera);
    $session->update(['instructor_id' => $diego->id]);

    $cancellation = app(CancelBooking::class)->handle(firstBookingOf($session), User::factory()->create());

    expect($cancellation->promoted?->is($javiera))->toBeTrue()
        ->and($diegoEntry->refresh()->status)->toBe(WaitlistStatus::Left);
});

it('stops waiting when she leaves, once', function () {
    $session = fullClassOnTuesday();
    $entry = waitFor($session, User::factory()->create());

    app(LeaveWaitlist::class)->handle($entry);

    expect($entry->refresh())
        ->status->toBe(WaitlistStatus::Left)
        ->resolved_at->not->toBeNull();
    expect(fn () => app(LeaveWaitlist::class)->handle($entry))
        ->toThrow(BookingRejected::class, 'Este lugar en la lista de espera ya no está vigente.');
});

it('stops waiting when she books the class herself', function () {
    $session = fullClassOnTuesday();
    $javiera = User::factory()->create();
    $entry = waitFor($session, $javiera);
    $this->travelTo(CarbonImmutable::parse('2026-11-03 13:00', 'America/Santiago'));
    app(CancelBooking::class)->handle(firstBookingOf($session), User::factory()->create());

    app(BookSession::class)->handle($session->refresh(), $javiera, BookingKind::Student, $javiera);

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Left);
});

it('closes the waitlist when the studio cancels the class', function () {
    $session = fullClassOnTuesday();
    $entry = waitFor($session, User::factory()->create());

    app(CancelSession::class)->handle($session, User::factory()->create(), 'Feriado');

    expect($entry->refresh()->status)->toBe(WaitlistStatus::Left);
});
