<?php

use App\Actions\Bookings\CancelSession;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\Payments\SubmitTransferReceipt;
use App\Actions\Payments\VoidPayment;
use App\Actions\Plans\SellPlan;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Models\CashRegister;
use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A class of the studio at the given local time.
 */
function homeClass(Studio $studio, ?User $instructor, string $localTime, array $attributes = []): ClassSession
{
    $startsAt = CarbonImmutable::parse($localTime, 'America/Santiago')->utc();

    return ClassSession::factory()->for($studio)->create([
        'class_type_id' => $studio->classTypes()->first() ?? ClassType::factory()->for($studio)->create(['name' => 'Pilates mat']),
        'instructor_id' => $instructor?->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(50),
        ...$attributes,
    ]);
}

/**
 * The booking of a student, made on Monday morning so every class of the
 * day can still be booked.
 */
function homeBooking(ClassSession $session, User $student): void
{
    test()->travelTo(CarbonImmutable::parse('2026-11-02 06:00', 'America/Santiago'));
    bookWithPlan($session, $student);
}

it('shows the studio home to every member', function (StudioRole $role) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('studios/home'));
})->with(StudioRole::cases());

it('gives each role its blocks in order, all built', function (StudioRole $role, array $blocks) {
    $studio = Studio::factory()->create();

    $this->actingAs(User::factory()->memberOf($studio, $role)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('blocks', $blocks));
})->with([
    'owner' => [StudioRole::Owner, [
        ['key' => 'today-occupancy', 'isBuilt' => true],
        ['key' => 'alerts', 'isBuilt' => true],
        ['key' => 'month-income', 'isBuilt' => true],
    ]],
    'front desk' => [StudioRole::FrontDesk, [
        ['key' => 'today-sessions', 'isBuilt' => true],
        ['key' => 'arrivals', 'isBuilt' => true],
        ['key' => 'receipts-to-review', 'isBuilt' => true],
        ['key' => 'cash-register', 'isBuilt' => true],
    ]],
    'instructor' => [StudioRole::Instructor, [['key' => 'teaching-today', 'isBuilt' => true]]],
    'student' => [StudioRole::Student, [
        ['key' => 'next-booking', 'isBuilt' => true],
        ['key' => 'classes-left', 'isBuilt' => true],
        ['key' => 'plan-expiry', 'isBuilt' => true],
    ]],
]);

it('gives every role only blocks whose data it is allowed to see', function (StudioRole $role) {
    foreach ($role->homeBlocks() as $block) {
        expect($role->allows($block->permission()))->toBeTrue("{$role->value} sees {$block->value}");
    }
})->with(StudioRole::cases());

it('shows the front desk today\'s classes and who arrives to the one on now', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create(['name' => 'Fernanda Díaz']);
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create(['name' => 'Javiera Morales']);
    $morning = homeClass($studio, $fernanda, '2026-11-02 09:00');
    $noon = homeClass($studio, $fernanda, '2026-11-02 12:00');
    $cancelled = homeClass($studio, $fernanda, '2026-11-02 19:00');
    homeClass($studio, $fernanda, '2026-11-03 09:00');
    homeBooking($noon, $javiera);
    app(CancelSession::class)->handle($cancelled, User::factory()->create(), 'Feriado');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:20', 'America/Santiago'));

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('todaySessions', fn ($sessions) => collect($sessions)->map(fn ($session) => [$session['id'], $session['startsAt'], $session['bookedCount'], $session['isCancelled']])->all() === [
                [$morning->id, '09:00', 0, false],
                [$noon->id, '12:00', 1, false],
                [$cancelled->id, '19:00', 0, true],
            ])
            ->where('arrivals.id', $noon->id)
            ->where('arrivals.isOn', true)
            ->where('arrivals.attendees.0.name', 'Javiera Morales')
            ->where('teachingToday', null)
            ->where('nextBooking', null));
});

it('tells the front desk when no classes are left today', function () {
    $studio = Studio::factory()->create();
    homeClass($studio, User::factory()->create(), '2026-11-02 09:00');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 20:00', 'America/Santiago'));

    $this->actingAs(User::factory()->memberOf($studio, StudioRole::FrontDesk)->create())
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('arrivals', null)->has('todaySessions', 1));
});

it('shows the instructor only her own classes of today, with their attendance', function () {
    $studio = Studio::factory()->create();
    $fernanda = User::factory()->memberOf($studio, StudioRole::Instructor)->create();
    $hers = homeClass($studio, $fernanda, '2026-11-02 09:00');
    homeClass($studio, User::factory()->create(), '2026-11-02 11:00');
    homeClass($studio, $fernanda, '2026-11-03 09:00');
    homeBooking($hers, User::factory()->memberOf($studio, StudioRole::Student)->create());
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'America/Santiago'));

    $this->actingAs($fernanda)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teachingToday', 1)
            ->where('teachingToday.0.id', $hers->id)
            ->where('teachingToday.0.attendanceIsOpen', true)
            ->has('teachingToday.0.attendees', 1)
            ->where('todaySessions', null)
            ->where('arrivals', null));
});

it('shows the student her next class in this studio and the waitlists she is in', function () {
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();
    $teacher = User::factory()->create(['name' => 'Diego Herrera']);
    $later = homeClass($studio, $teacher, '2026-11-04 19:00');
    $sooner = homeClass($studio, $teacher, '2026-11-03 19:00');
    homeBooking($later, $javiera);
    homeBooking($sooner, $javiera);
    homeBooking(homeClass(Studio::factory()->create(), $teacher, '2026-11-02 20:00'), $javiera);
    WaitlistEntry::factory()->for(homeClass($studio, $teacher, '2026-11-05 19:00'))->for($javiera)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));

    $this->actingAs($javiera)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextBooking.id', $sooner->id)
            ->where('nextBooking.date', 'martes 3 de noviembre')
            ->where('nextBooking.isToday', false)
            ->where('nextBooking.instructorName', 'Diego Herrera')
            ->where('nextBooking.freeCancellationUntil', 'las 13:00')
            ->where('waitingCount', 1)
            ->where('planSummary.isUnlimited', true)
            ->where('todaySessions', null));
});

it('tells the student how many classes she has left and until when', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'America/Santiago'));
    $studio = Studio::factory()->create();
    $javiera = User::factory()->memberOf($studio, StudioRole::Student)->create();

    $this->actingAs($javiera)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('planSummary', null));

    PlanPurchase::factory()->for($studio)->for($javiera)->create(['credits_left' => 5]);
    PlanPurchase::factory()->for($studio)->for($javiera)->create(['credits' => 4, 'credits_left' => 2, 'validity_days' => 10, 'expires_at' => now('America/Santiago')->startOfDay()->addDays(10)->utc()]);
    PlanPurchase::factory()->for($studio)->for($javiera)->expired()->create();

    $this->actingAs($javiera)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page->where('planSummary', [
            'isUnlimited' => false,
            'classesLeft' => 7,
            'lastDay' => 'martes 1 de diciembre',
            'daysLeft' => 30,
            'expiresSoon' => false,
            'next' => null,
        ]));
});

it('shows the front desk the receipts to review and the state of her register', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 16:00', 'America/Santiago'));
    $studio = Studio::factory()->create(['transfer_details' => 'Banco del Ejemplo']);
    $camila = User::factory()->memberOf($studio, StudioRole::FrontDesk)->create();
    $plan = Plan::factory()->for($studio)->create(['name' => '8 clases al mes', 'price' => 45000]);
    Storage::fake();
    app(SubmitTransferReceipt::class)->handle($plan, User::factory()->create(['name' => 'Ignacia Navarro']), UploadedFile::fake()->image('comprobante.png'));
    $page = fn () => $this->actingAs($camila)->get(route('studios.home', $studio));

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('receiptsToReview.count', 1)
        ->where('receiptsToReview.oldest.0.studentName', 'Ignacia Navarro')
        ->where('cashRegister', ['state' => 'none'])
        ->where('alerts', null));

    $register = CashRegister::factory()->for($studio)->for($camila)->onDaysAgo(1)->create();
    $page()->assertInertia(fn (Assert $page) => $page->where('cashRegister.state', 'stale'));

    $register->delete();
    $today = CashRegister::factory()->for($studio)->for($camila)->create();
    app(SellPlan::class)->handle($plan, User::factory()->create(), $today, PaymentMethod::Card);
    $page()->assertInertia(fn (Assert $page) => $page
        ->where('cashRegister.state', 'open')
        ->where('cashRegister.totals', ['cash' => 0, 'card' => 45000, 'transfer' => 0]));
});

it('alerts the owner of receipts, registers left open or short, and today\'s voids', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 16:00', 'America/Santiago'));
    $studio = Studio::factory()->create();
    $carla = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $camila = User::factory()->create(['name' => 'Camila Soto']);
    $forgotten = CashRegister::factory()->for($studio)->for($camila)->onDaysAgo(2)->create();
    $short = CashRegister::factory()->for($studio)->for($carla)->onDaysAgo(1)->create(['opening_cash' => 20000]);
    app(CloseCashRegister::class)->handle($short, 18000, 'Faltó sencillo');
    CashRegister::factory()->for($studio)->for($carla)->onDaysAgo(9)->closed()->create();
    $today = CashRegister::factory()->for($studio)->for($carla)->create();
    $sold = app(SellPlan::class)->handle(Plan::factory()->for($studio)->create(['price' => 28000]), User::factory()->create(['name' => 'Rosa Fuentes']), $today, PaymentMethod::Cash);
    app(VoidPayment::class)->handle($sold->payments()->sole(), $carla, 'Error');

    $this->actingAs($carla)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.receiptsToReview', 0)
            ->where('alerts.openRegisters.0.personName', 'Camila Soto')
            ->has('alerts.openRegisters', 1)
            ->where('alerts.registersWithDifference', fn ($registers) => collect($registers)->map(fn ($register) => [$register['difference'], $register['note']])->all() === [[-2000, 'Faltó sencillo']])
            ->where('alerts.voidedToday.0.studentName', 'Rosa Fuentes')
            ->where('alerts.voidedToday.0.reason', 'Error')
            ->where('receiptsToReview', null));

    expect($forgotten->refresh()->isClosed())->toBeFalse();
});

it('shows the owner the month\'s income, against the same stretch of last month', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 14:00', 'America/Santiago'));
    $studio = Studio::factory()->create();
    $carla = User::factory()->memberOf($studio, StudioRole::Owner)->create();
    $paid = fn (string $at, int $amount, PaymentMethod $method, array $attributes = []) => Payment::factory()->create([
        'plan_purchase_id' => PlanPurchase::factory()->for($studio),
        'amount' => $amount,
        'method' => $method,
        'paid_at' => CarbonImmutable::parse($at, 'America/Santiago')->utc(),
        ...$attributes,
    ]);
    $paid('2026-11-03 10:00', 45000, PaymentMethod::Cash);
    $paid('2026-11-10 18:00', 28000, PaymentMethod::Card);
    $paid('2026-11-12 18:00', 10000, PaymentMethod::Cash, ['status' => 'voided', 'voided_at' => now(), 'status_reason' => 'Error']);
    $paid('2026-10-05 10:00', 20000, PaymentMethod::Cash);
    $paid('2026-10-20 10:00', 99000, PaymentMethod::Card);
    Payment::factory()->create(['amount' => 50000]);

    $this->actingAs($carla)
        ->get(route('studios.home', $studio))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthIncome.total', 73000)
            ->where('monthIncome.previous', 20000)
            ->where('monthIncome.byMethod', fn ($methods) => collect($methods)->pluck('total', 'method')->all() === ['cash' => 45000, 'card' => 28000, 'transfer' => 0, 'webpay' => 0]));
});

it('answers 404 to people outside the studio', function () {
    $outsider = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    $this->actingAs($outsider)
        ->get(route('studios.home', Studio::factory()->create()))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});

it('answers 404 for a studio that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->get('/estudios/no-existe')
        ->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get(route('studios.home', Studio::factory()->create()))
        ->assertRedirect(route('login'));
});
