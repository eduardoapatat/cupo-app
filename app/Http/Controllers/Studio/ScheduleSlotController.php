<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Classes\ApplyScheduleChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\ScheduleSlotRequest;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * The classes of the weekly schedule. Every change brings the sessions that
 * nobody booked yet in line with it (see ApplyScheduleChange).
 */
class ScheduleSlotController extends Controller
{
    #[Authorize('schedule.manage', 'studio')]
    public function store(ScheduleSlotRequest $request, Studio $studio, ApplyScheduleChange $applyScheduleChange): RedirectResponse
    {
        $slot = $studio->scheduleSlots()->create($request->slotData());

        $applyScheduleChange->handle($studio, [$slot]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We added the class to the schedule.')]);

        return to_route('studios.classes.index', $studio);
    }

    #[Authorize('schedule.manage', 'studio')]
    public function update(ScheduleSlotRequest $request, Studio $studio, ScheduleSlot $scheduleSlot, ApplyScheduleChange $applyScheduleChange): RedirectResponse
    {
        $scheduleSlot->update($request->slotData());

        $applyScheduleChange->handle($studio, [$scheduleSlot]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We saved the schedule. Classes that already have bookings stay as they were.')]);

        return to_route('studios.classes.index', $studio);
    }

    /**
     * Take the class off the schedule. Its future sessions without bookings
     * go away; the ones with bookings stay, detached from the schedule.
     */
    #[Authorize('schedule.manage', 'studio')]
    public function destroy(Studio $studio, ScheduleSlot $scheduleSlot): RedirectResponse
    {
        DB::transaction(function () use ($scheduleSlot): void {
            $scheduleSlot->classSessions()->unbookedAhead()->delete();

            $scheduleSlot->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We took the class off the schedule. Classes that already have bookings stay.')]);

        return to_route('studios.classes.index', $studio);
    }
}
