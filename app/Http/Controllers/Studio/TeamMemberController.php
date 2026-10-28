<?php

namespace App\Http\Controllers\Studio;

use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Take someone off the team: they lose access to the studio at once.
     * Only front desk and instructors; the owner cannot remove herself, and
     * students are not part of «Equipo», so both answer 404.
     *
     * The schedule and the future sessions they taught stay without an
     * instructor until someone is assigned; students cannot book them
     * meanwhile. Past sessions keep who taught them.
     */
    #[Authorize('staff.manage', 'studio')]
    public function destroy(Studio $studio, Membership $membership): RedirectResponse
    {
        abort_unless(in_array($membership->role, StudioRole::invitable(), true), 404);

        DB::transaction(function () use ($studio, $membership): void {
            $studio->scheduleSlots()->where('instructor_id', $membership->user_id)->update(['instructor_id' => null]);
            $studio->classSessions()
                ->where('instructor_id', $membership->user_id)
                ->where('starts_at', '>', now())
                ->update(['instructor_id' => null]);

            $membership->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is no longer part of the team.', ['name' => $membership->user->name])]);

        return to_route('studios.team.index', $studio);
    }
}
