<?php

namespace Database\Seeders;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Classes\GenerateSessions;
use App\Actions\Plans\TakeClassFromPlan;
use App\Actions\Studios\CreateStudio;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\Commune;
use App\Enums\PaymentMethod;
use App\Enums\StudioRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoStudioSeeder extends Seeder
{
    /**
     * Fictional students of Estudio Flow besides Javiera and Rosa, so its
     * classes have people in them.
     */
    private const array STUDENTS = [
        'Antonia Castro',
        'Florencia Rivas',
        'Isidora Muñoz',
        'Catalina Vega',
        'Francisca Torres',
        'Martina Silva',
        'Josefa Reyes',
        'Ignacia Navarro',
    ];

    /**
     * Estudio Flow, the fictional studio of the docs, created by its owner
     * the way the app does it, with one demo account per role (two
     * instructors), Rosa, who pays in cash and does not use the app, and
     * more students. It has a weekly schedule, classes from two days ago,
     * bookings, attendance and a full class with Javiera on its waitlist.
     * Fernanda also teaches at Estudio Sur, so the studio switcher has
     * something to switch. Every account uses the password "password".
     */
    public function run(): void
    {
        $createStudio = new CreateStudio;

        $carla = User::factory()->create(['name' => 'Carla Rojas', 'email' => 'carla@example.com']);
        $flow = $createStudio->handle($carla, 'Estudio Flow', Commune::Nunoa);

        $people = [
            ['Camila Soto', 'camila@example.com', StudioRole::FrontDesk],
            ['Fernanda Díaz', 'fernanda@example.com', StudioRole::Instructor],
            ['Diego Herrera', 'diego@example.com', StudioRole::Instructor],
            ['Javiera Morales', 'javiera@example.com', StudioRole::Student],
        ];

        foreach ($people as [$name, $email, $role]) {
            User::factory()->memberOf($flow, $role)->create([
                'name' => $name,
                'email' => $email,
            ]);
        }

        User::factory()->withoutApp()->memberOf($flow, StudioRole::Student)->create([
            'name' => 'Rosa Fuentes',
            'phone' => '+56987654321',
        ]);

        foreach (self::STUDENTS as $name) {
            User::factory()->memberOf($flow, StudioRole::Student)->create(['name' => $name]);
        }

        $this->scheduleOf($flow);
        $this->plansOf($flow);
        $this->bookingsOf($flow);

        $paula = User::factory()->create(['name' => 'Paula Vidal', 'email' => 'paula@example.com']);
        $sur = $createStudio->handle($paula, 'Estudio Sur', Commune::LaFlorida);

        User::where('email', 'fernanda@example.com')->sole()
            ->memberships()->create(['studio_id' => $sur->id, 'role' => StudioRole::Instructor]);
    }

    /**
     * Three class types and a weekly schedule from Monday to Saturday, and
     * its classes from two days ago (to show attendance) to the horizon.
     */
    private function scheduleOf(Studio $flow): void
    {
        $fernanda = User::where('email', 'fernanda@example.com')->sole();
        $diego = User::where('email', 'diego@example.com')->sole();

        $pilates = $flow->classTypes()->create(['name' => 'Pilates mat', 'duration_minutes' => 50, 'capacity' => 10]);
        $yoga = $flow->classTypes()->create(['name' => 'Yoga', 'duration_minutes' => 60, 'capacity' => 8]);
        $functional = $flow->classTypes()->create(['name' => 'Funcional', 'duration_minutes' => 45, 'capacity' => 12]);

        $week = [];

        foreach ([1, 3, 5] as $weekday) {
            $week[] = [$weekday, '07:00', $functional, $diego];
            $week[] = [$weekday, '09:00', $pilates, $fernanda];
            $week[] = [$weekday, '19:00', $pilates, $fernanda];
            $week[] = [$weekday, '20:00', $yoga, $diego];
        }

        foreach ([2, 4] as $weekday) {
            $week[] = [$weekday, '08:00', $yoga, $diego];
            $week[] = [$weekday, '18:30', $functional, $diego];
            $week[] = [$weekday, '19:30', $pilates, $fernanda];
        }

        $week[] = [6, '10:00', $yoga, $fernanda];
        $week[] = [6, '11:30', $pilates, $fernanda];

        foreach ($week as [$weekday, $time, $classType, $instructor]) {
            $flow->scheduleSlots()->create([
                'class_type_id' => $classType->id,
                'weekday' => $weekday,
                'starts_at' => $time,
                'instructor_id' => $instructor->id,
            ]);
        }

        $generateSessions = new GenerateSessions;
        // From the start of that day, whatever time it is seeded.
        $generateSessions->handle($flow, now(config()->string('studio.timezone'))->subDays(2)->startOfDay());
        $generateSessions->handle($flow);
    }

    /**
     * The studio's plans, and the ones its students bought ten days ago:
     * Antonia has the unlimited one, Rosa 4 classes, Ignacia a plan that
     * already expired, and the rest 8 classes. Paid at the counter.
     */
    private function plansOf(Studio $flow): void
    {
        $camila = User::where('email', 'camila@example.com')->sole();
        $pack = $flow->plans()->create(['name' => '8 clases al mes', 'price' => 45000, 'credits' => 8, 'validity_days' => 30]);
        $small = $flow->plans()->create(['name' => '4 clases al mes', 'price' => 28000, 'credits' => 4, 'validity_days' => 30]);
        $unlimited = $flow->plans()->create([
            'name' => 'Ilimitado',
            'price' => 65000,
            'credits' => null,
            'validity_days' => 30,
            'daily_limit' => config()->integer('studio.defaults.unlimited_plan_daily_limit'),
        ]);
        $trial = $flow->plans()->create(['name' => 'Clase de prueba', 'price' => 6000, 'credits' => 1, 'validity_days' => 7]);
        $trial->forceFill(['archived_at' => now()])->save();

        $students = $flow->memberships()->where('role', StudioRole::Student)->with('user')->get()->map->user;

        foreach ($students as $student) {
            match ($student->name) {
                'Antonia Castro' => $this->sell($unlimited, $student, $camila, daysAgo: 10),
                'Rosa Fuentes' => $this->sell($small, $student, $camila, daysAgo: 10, method: PaymentMethod::Cash),
                'Ignacia Navarro' => $this->sell($small, $student, $camila, daysAgo: 45, method: PaymentMethod::Transfer),
                default => $this->sell($pack, $student, $camila, daysAgo: 10, method: PaymentMethod::Card),
            };
        }
    }

    /**
     * A sale made some days ago, as SellPlan would have left it then.
     */
    private function sell(Plan $plan, User $student, User $seller, int $daysAgo, PaymentMethod $method = PaymentMethod::Cash): void
    {
        $soldOn = now(config()->string('studio.timezone'))->startOfDay()->subDays($daysAgo);
        $soldAt = $soldOn->setTime(11, 0)->utc();

        $purchase = PlanPurchase::create([
            'studio_id' => $plan->studio_id,
            'user_id' => $student->id,
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'price' => $plan->price,
            'credits' => $plan->credits,
            'credits_left' => $plan->credits,
            'validity_days' => $plan->validity_days,
            'daily_limit' => $plan->daily_limit,
            'starts_on' => $soldOn->toDateString(),
            'expires_at' => $soldOn->addDays($plan->validity_days)->utc(),
            'sold_by_id' => $seller->id,
        ]);
        $purchase->forceFill(['created_at' => $soldAt, 'updated_at' => $soldAt])->save();

        $payment = $purchase->payments()->create([
            'studio_id' => $plan->studio_id,
            'user_id' => $student->id,
            'amount' => $plan->price,
            'method' => $method,
            'received_by_id' => $seller->id,
        ]);
        $payment->forceFill(['created_at' => $soldAt, 'updated_at' => $soldAt])->save();
    }

    /**
     * People in the classes of the next few days, and in the last two:
     * those of two days ago already closed their attendance (one missed
     * it), and yesterday's can still be marked. Each class comes from the
     * student's plan, so whoever has no classes left for a day is left out.
     * Javiera goes to one class in four and keeps at least 3 classes of her
     * plan to book in the demo. The next Yoga that is not too close fills up
     * first, with Javiera waiting for a spot.
     */
    private function bookingsOf(Studio $flow): void
    {
        $timezone = config()->string('studio.timezone');
        $javiera = User::where('email', 'javiera@example.com')->sole();
        $others = $flow->memberships()
            ->where('role', StudioRole::Student)
            ->where('user_id', '!=', $javiera->id)
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map->user;

        $full = $flow->classSessions()
            ->upcoming()
            ->with('classType', 'studio')
            ->whereRelation('classType', 'name', 'Yoga')
            ->orderBy('starts_at')
            ->get()
            ->first(fn (ClassSession $session): bool => now()->lessThan($session->waitlistClosesAt()));

        if ($full !== null) {
            $this->seat($full, $others);
            app(JoinWaitlist::class)->handle($full->refresh(), $javiera, $javiera);
        }

        $sessions = $flow->classSessions()
            ->with('classType', 'studio')
            ->where('starts_at', '<', now($timezone)->startOfDay()->addDays(4)->utc())
            ->orderBy('starts_at')
            ->get();

        foreach ($sessions->values() as $index => $session) {
            if ($session->is($full)) {
                continue;
            }

            // A different handful of people in each class.
            $attendees = $others->slice($index % 3, 2 + $index % 5);

            if ($index % 4 === 0 && $javiera->planPurchases()->sum('credits_left') > 3) {
                $attendees->push($javiera);
            }

            $this->seat($session, $attendees, missed: $index === 1);
        }
    }

    /**
     * Book the people in the class, up to its capacity, with a class of
     * their plan. A class still open goes through BookSession; one already
     * closed gets its bookings as they would be now, with their attendance.
     * Whoever has no plan to pay for it is left out.
     *
     * @param  Collection<int, User>  $people
     */
    private function seat(ClassSession $session, Collection $people, bool $missed = false): void
    {
        $isOpen = now()->lessThan($session->bookingClosesAt());
        $attendanceClosed = now()->greaterThan($session->attendanceClosesAt());
        $seated = 0;

        foreach ($people->values() as $person) {
            if ($seated >= $session->capacity) {
                return;
            }

            try {
                if ($isOpen) {
                    app(BookSession::class)->handle($session, $person, BookingKind::Student, $person);
                } else {
                    $this->seatInThePast($session, $person, match (true) {
                        $missed && $seated === 0 => BookingStatus::NoShow,
                        $attendanceClosed => BookingStatus::Attended,
                        default => BookingStatus::Confirmed,
                    });
                }

                $seated++;
            } catch (BookingRejected) {
                continue;
            }
        }
    }

    /**
     * A booking of a class that already closed, with its class taken from
     * the plan as BookSession would have taken it then.
     *
     * @throws BookingRejected when no plan can pay for it.
     */
    private function seatInThePast(ClassSession $session, User $person, BookingStatus $status): void
    {
        DB::transaction(function () use ($session, $person, $status): void {
            $takeClassFromPlan = app(TakeClassFromPlan::class);
            $purchase = $takeClassFromPlan->handle($session, $person, $person);

            $booking = Booking::factory()->for($session)->for($person)->create([
                'booked_by_id' => $person->id,
                'plan_purchase_id' => $purchase->id,
                'status' => $status,
            ]);
            $takeClassFromPlan->record($purchase, $booking, $person);

            ClassSession::query()->whereKey($session->id)->increment('booked_count');
        });
    }
}
