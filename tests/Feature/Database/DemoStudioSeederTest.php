<?php

use App\Enums\BookingStatus;
use App\Enums\StudioRole;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;

it('seeds Estudio Flow with a demo account for every role and a student without the app', function () {
    $this->seed();

    $studio = Studio::where('slug', 'estudio-flow')->sole();
    $roles = $studio->memberships()->with('user')->orderBy('id')->get()
        ->mapWithKeys(fn ($membership) => [$membership->user->name => $membership->role]);

    expect($roles->all())->toBe([
        'Carla Rojas' => StudioRole::Owner,
        'Camila Soto' => StudioRole::FrontDesk,
        'Fernanda Díaz' => StudioRole::Instructor,
        'Diego Herrera' => StudioRole::Instructor,
        'Javiera Morales' => StudioRole::Student,
        'Rosa Fuentes' => StudioRole::Student,
        'Antonia Castro' => StudioRole::Student,
        'Florencia Rivas' => StudioRole::Student,
        'Isidora Muñoz' => StudioRole::Student,
        'Catalina Vega' => StudioRole::Student,
        'Francisca Torres' => StudioRole::Student,
        'Martina Silva' => StudioRole::Student,
        'Josefa Reyes' => StudioRole::Student,
        'Ignacia Navarro' => StudioRole::Student,
    ])
        ->and(User::where('name', 'Rosa Fuentes')->sole()->usesApp())->toBeFalse();
});

it('seeds a second studio where Fernanda also teaches', function () {
    $this->seed();

    $sur = Studio::where('slug', 'estudio-sur')->sole();

    expect(User::where('email', 'fernanda@example.com')->sole()->roleIn($sur))->toBe(StudioRole::Instructor)
        ->and(User::where('email', 'paula@example.com')->sole()->roleIn($sur))->toBe(StudioRole::Owner);
});

it('seeds the weekly schedule, past and upcoming bookings and a full class with Javiera waiting', function () {
    // Wednesday, November 4, at noon in Santiago.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Santiago'));

    $this->seed();

    $flow = Studio::where('slug', 'estudio-flow')->sole();
    $twoDaysAgo = CarbonImmutable::parse('2026-11-02', 'America/Santiago');
    $javiera = User::where('email', 'javiera@example.com')->sole();
    $sessions = $flow->classSessions()->get();

    expect($flow->classTypes()->pluck('name')->sort()->values()->all())->toBe(['Funcional', 'Pilates mat', 'Yoga'])
        ->and($flow->scheduleSlots()->count())->toBe(20)
        ->and($sessions->min('starts_at')->timezone('America/Santiago')->toDateString())->toBe($twoDaysAgo->toDateString())
        ->and($sessions->every(fn (ClassSession $session) => $session->booked_count === $session->bookings()->current()->count()))->toBeTrue();

    $statuses = Booking::query()
        ->whereIn('class_session_id', $flow->classSessions()->where('starts_at', '<', $twoDaysAgo->addDay()->utc())->select('id'))
        ->pluck('status');
    expect($statuses->map(fn (BookingStatus $status) => $status->value)->unique()->sort()->values()->all())->toBe(['attended', 'no_show']);

    $yesterday = $flow->classSessions()
        ->where('starts_at', '>=', $twoDaysAgo->addDay()->utc())
        ->where('starts_at', '<', $twoDaysAgo->addDays(2)->utc())
        ->get();
    expect($yesterday->every(fn (ClassSession $session) => $session->attendanceIsOpen()))->toBeTrue()
        ->and(Booking::whereIn('class_session_id', $yesterday->modelKeys())->pluck('status')->unique()->all())->toBe([BookingStatus::Confirmed]);

    $entry = $javiera->waitlistEntries()->sole();
    expect($entry->status)->toBe(WaitlistStatus::Waiting)
        ->and($entry->classSession->classType->name)->toBe('Yoga')
        ->and($entry->classSession->booked_count)->toBe($entry->classSession->capacity)
        ->and($javiera->bookings()->current()->whereRelation('classSession', 'starts_at', '>', now())->exists())->toBeTrue();
});

it('seeds at any time of the week, with the classes of two days ago from the morning', function (string $now) {
    $this->travelTo(CarbonImmutable::parse($now, 'America/Santiago'));

    $this->seed();

    $first = Studio::where('slug', 'estudio-flow')->sole()->classSessions()->orderBy('starts_at')->first();
    expect(User::where('email', 'javiera@example.com')->sole()->waitlistEntries()->count())->toBe(1)
        ->and($first?->localStartsAt()->toDateString())->toBe(now('America/Santiago')->subDays(2)->toDateString());
})->with(['2026-11-04 23:30', '2026-11-05 06:00', '2026-11-07 21:00', '2026-11-04 19:55']);

it('sells the studio\'s plans to its students and takes each booking from them', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Santiago'));

    $this->seed();

    $flow = Studio::where('slug', 'estudio-flow')->sole();
    $purchases = $flow->planPurchases()->with('user')->get();
    $studentBookings = Booking::query()
        ->whereIn('class_session_id', $flow->classSessions()->select('id'))
        ->where('kind', 'student')
        ->get();

    expect($flow->plans()->active()->pluck('name')->sort()->values()->all())->toBe(['4 clases al mes', '8 clases al mes', 'Ilimitado'])
        ->and($flow->plans()->whereNotNull('archived_at')->pluck('name')->all())->toBe(['Clase de prueba'])
        ->and($purchases)->toHaveCount(10)
        ->and($purchases->firstWhere('user.name', 'Ignacia Navarro')->expires_at->isPast())->toBeTrue()
        ->and($purchases->firstWhere('user.name', 'Antonia Castro')->isUnlimited())->toBeTrue()
        ->and($flow->planPurchases()->has('payments')->count())->toBe(10)
        ->and($studentBookings->every(fn (Booking $booking) => $booking->plan_purchase_id !== null))->toBeTrue()
        ->and($purchases->reject->isUnlimited()->every(
            fn ($purchase) => $purchase->credits_left === $purchase->credits - $purchase->bookings()->count(),
        ))->toBeTrue();
});
