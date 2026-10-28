<?php

namespace App\Actions\Classes;

use App\Models\ClassSession;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GenerateSessions
{
    /**
     * Create the sessions of the studio's weekly schedule from today up to
     * the days ahead students can book (booking_horizon_days). Each local
     * weekday and time (America/Santiago) becomes UTC with that date's own
     * offset, so daylight saving changes are handled.
     *
     * Running it again creates nothing new: the insert skips a session that
     * already exists for the slot (unique slot and start) or that would
     * overlap another one (exclusion constraint), via ON CONFLICT DO NOTHING.
     *
     * @return int How many sessions it created.
     */
    public function handle(Studio $studio, ?CarbonInterface $now = null): int
    {
        $timezone = config()->string('studio.timezone');
        $now = CarbonImmutable::instance($now ?? now());
        $today = $now->timezone($timezone)->startOfDay();

        $slots = $studio->scheduleSlots()
            ->with('classType')
            ->whereRelation('classType', 'is_active', true)
            ->get();

        $rows = [];

        for ($day = 0; $day < $studio->booking_horizon_days; $day++) {
            $date = $today->addDays($day);

            foreach ($slots->where('weekday', $date->dayOfWeekIso) as $slot) {
                $startsAt = CarbonImmutable::parse("{$date->toDateString()} {$slot->starts_at}", $timezone)->utc();

                if ($startsAt->lessThanOrEqualTo($now)) {
                    continue;
                }

                $rows[] = $this->row($studio, $slot, $startsAt, $now);
            }
        }

        return $rows === [] ? 0 : ClassSession::query()->insertOrIgnore($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Studio $studio, ScheduleSlot $slot, CarbonImmutable $startsAt, CarbonImmutable $now): array
    {
        return [
            'studio_id' => $studio->id,
            'class_type_id' => $slot->class_type_id,
            'schedule_slot_id' => $slot->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($slot->classType->duration_minutes),
            'capacity' => $slot->classType->capacity,
            'instructor_id' => $slot->instructor_id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
