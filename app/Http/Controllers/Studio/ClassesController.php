<?php

namespace App\Http\Controllers\Studio;

use App\Enums\StudioRole;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Models\ClassType;
use App\Models\Membership;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Clases»: the owner's class types and weekly schedule. Sessions are
 * generated from the schedule (see GenerateSessions).
 */
class ClassesController extends Controller
{
    #[Authorize('schedule.manage', 'studio')]
    public function index(Studio $studio): Response
    {
        $classTypes = $studio->classTypes()->orderByDesc('is_active')->orderByRaw('lower(name)')->orderBy('id')->get();

        $slots = $studio->scheduleSlots()
            ->with(['classType', 'instructor'])
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();

        $teachers = $studio->memberships()
            ->with('user')
            ->whereIn('role', [StudioRole::Owner, StudioRole::Instructor])
            ->get()
            ->sortBy(fn (Membership $membership): string => mb_strtolower($membership->user->name));

        return Inertia::render('studios/classes', [
            'classTypes' => $classTypes->map(fn (ClassType $classType): array => [
                'id' => $classType->id,
                'name' => $classType->name,
                'durationMinutes' => $classType->duration_minutes,
                'capacity' => $classType->capacity,
                'isActive' => $classType->is_active,
            ]),
            'slots' => $slots->map(fn (ScheduleSlot $slot): array => [
                'id' => $slot->id,
                'weekday' => $slot->weekday,
                'startsAt' => substr($slot->starts_at, 0, 5),
                'classTypeId' => $slot->class_type_id,
                'classTypeName' => $slot->classType->name,
                'durationMinutes' => $slot->classType->duration_minutes,
                'instructorId' => $slot->instructor_id,
                'instructorName' => $slot->instructor?->name,
            ]),
            'weekdays' => Weekday::options(),
            'bookingHorizonDays' => $studio->booking_horizon_days,
            'teachers' => $teachers->values()->map(fn (Membership $membership): array => [
                'value' => (string) $membership->user_id,
                'label' => $membership->user->name,
            ]),
        ]);
    }
}
