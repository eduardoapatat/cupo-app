<?php

namespace Database\Seeders;

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Classes\GenerateSessions;
use App\Actions\Studios\CreateStudio;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\Commune;
use App\Enums\StudioRole;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoStudioSeeder extends Seeder
{
    /**
     * Fictional students of Estudio Flow besides Javiera and Rosa, so its
     * classes have people in them.
     */
    private const array STUDENTS = [
        'Antonia Castro',
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
     * People in the classes of the next few days, and in the last two:
     * those of two days ago already closed their attendance (one missed
     * it), and yesterday's can still be marked. The next Yoga that is not
     * too close fills up, with Javiera waiting for a spot.
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

        $sessions = $flow->classSessions()
            ->with('classType', 'studio')
            ->where('starts_at', '<', now($timezone)->startOfDay()->addDays(4)->utc())
            ->orderBy('starts_at')
            ->get();

        $full = $flow->classSessions()
            ->upcoming()
            ->whereRelation('classType', 'name', 'Yoga')
            ->orderBy('starts_at')
            ->get()
            ->first(fn (ClassSession $session): bool => now()->lessThan($session->waitlistClosesAt()));

        foreach ($sessions->values() as $index => $session) {
            if ($session->is($full)) {
                continue;
            }

            // A different handful of people in each class.
            $attendees = $others->slice($index % 3, 2 + $index % 5);

            if ($index % 2 === 0) {
                $attendees->push($javiera);
            }

            $this->seat($session, $attendees, missed: $index === 1);
        }

        if ($full !== null) {
            $this->seat($full, $others->take($full->capacity));
            app(JoinWaitlist::class)->handle($full->refresh(), $javiera, $javiera);
        }
    }

    /**
     * Book the people in the class. A class still open goes through
     * BookSession; one already closed gets its bookings as they would be
     * now, with their attendance.
     *
     * @param  Collection<int, User>  $people
     */
    private function seat(ClassSession $session, Collection $people, bool $missed = false): void
    {
        if (now()->lessThan($session->bookingClosesAt())) {
            foreach ($people as $person) {
                app(BookSession::class)->handle($session, $person, BookingKind::Student, $person);
            }

            return;
        }

        $attendanceClosed = now()->greaterThan($session->attendanceClosesAt());

        foreach ($people->values() as $index => $person) {
            Booking::factory()->for($session)->for($person)->create([
                'booked_by_id' => $person->id,
                'status' => match (true) {
                    $missed && $index === 0 => BookingStatus::NoShow,
                    $attendanceClosed => BookingStatus::Attended,
                    default => BookingStatus::Confirmed,
                },
            ]);
        }

        ClassSession::query()->whereKey($session->id)->update(['booked_count' => $people->count()]);
    }
}
