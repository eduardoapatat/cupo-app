<?php

namespace App\Actions\Classes;

use App\Models\ScheduleSlot;
use App\Models\Studio;
use Illuminate\Support\Facades\DB;

class ApplyScheduleChange
{
    public function __construct(private GenerateSessions $generateSessions) {}

    /**
     * Bring the generated sessions in line with a changed schedule. The
     * future sessions of the changed slots that nobody booked yet are
     * removed and generated again from the new schedule; a session that
     * already has bookings stays as it was, so nobody's class moves under
     * their feet. Changing that one session is done from the agenda.
     *
     * @param  iterable<ScheduleSlot>  $changedSlots
     */
    public function handle(Studio $studio, iterable $changedSlots): void
    {
        DB::transaction(function () use ($studio, $changedSlots): void {
            $slotIds = collect($changedSlots)->map(fn (ScheduleSlot $slot): int => $slot->id)->all();

            $studio->classSessions()->whereIn('schedule_slot_id', $slotIds)->unbookedAhead()->delete();

            $this->generateSessions->handle($studio);
        });
    }
}
