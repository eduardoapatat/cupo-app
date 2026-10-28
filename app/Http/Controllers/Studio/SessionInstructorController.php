<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\SubstituteInstructorRequest;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class SessionInstructorController extends Controller
{
    /**
     * Someone else teaches this class only, for example when the instructor
     * is sick. The weekly schedule does not change.
     */
    #[Authorize('sessions.substitute', 'studio')]
    public function update(SubstituteInstructorRequest $request, Studio $studio, ClassSession $classSession): RedirectResponse
    {
        if ($classSession->status === ClassSessionStatus::Cancelled || now()->greaterThanOrEqualTo($classSession->starts_at)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This class can no longer change its instructor.')]);

            return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
        }

        $instructor = User::findOrFail($request->integer('instructor_id'));

        if ($classSession->bookings()->current()->where('user_id', $instructor->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':name already has a booking in this class. Cancel it first.', ['name' => $instructor->name])]);

            return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
        }

        $classSession->update(['instructor_id' => $instructor->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name teaches this class.', ['name' => $instructor->name])]);

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }
}
