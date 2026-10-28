<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Classes\ApplyScheduleChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\ClassTypeRequest;
use App\Models\ClassType;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class ClassTypeController extends Controller
{
    #[Authorize('schedule.manage', 'studio')]
    public function store(ClassTypeRequest $request, Studio $studio): RedirectResponse
    {
        $classType = $studio->classTypes()->create([
            'name' => $request->className(),
            'duration_minutes' => $request->integer('duration_minutes'),
            'capacity' => $request->integer('capacity'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We added :class.', ['class' => $classType->name])]);

        return to_route('studios.classes.index', $studio);
    }

    /**
     * Change a class type, or stop teaching it. Its future sessions without
     * bookings follow the change (see ApplyScheduleChange).
     */
    #[Authorize('schedule.manage', 'studio')]
    public function update(ClassTypeRequest $request, Studio $studio, ClassType $classType, ApplyScheduleChange $applyScheduleChange): RedirectResponse
    {
        $classType->update([
            'name' => $request->className(),
            'duration_minutes' => $request->integer('duration_minutes'),
            'capacity' => $request->integer('capacity'),
            'is_active' => $request->boolean('is_active', $classType->is_active),
        ]);

        $applyScheduleChange->handle($studio, $classType->scheduleSlots);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We saved :class.', ['class' => $classType->name])]);

        return to_route('studios.classes.index', $studio);
    }
}
