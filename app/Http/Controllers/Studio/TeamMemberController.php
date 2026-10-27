<?php

namespace App\Http\Controllers\Studio;

use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Take someone off the team: they lose access to the studio at once.
     * Only front desk and instructors; the owner cannot remove herself, and
     * students are not part of «Equipo», so both answer 404.
     */
    #[Authorize('staff.manage', 'studio')]
    public function destroy(Studio $studio, Membership $membership): RedirectResponse
    {
        abort_unless(in_array($membership->role, StudioRole::invitable(), true), 404);

        $membership->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is no longer part of the team.', ['name' => $membership->user->name])]);

        return to_route('studios.team.index', $studio);
    }
}
