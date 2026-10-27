<?php

namespace App\Http\Controllers\Studio;

use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Studio;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Equipo»: the owner, the front desk and the instructors of the studio,
 * and the invitations not accepted yet. Students are in «Alumnas».
 */
class TeamController extends Controller
{
    #[Authorize('staff.manage', 'studio')]
    public function index(Studio $studio): Response
    {
        $staffRoles = [StudioRole::Owner, ...StudioRole::invitable()];

        $members = $studio->memberships()
            ->with('user:id,name,email')
            ->whereIn('role', $staffRoles)
            ->get()
            ->sortBy(fn (Membership $membership): array => [
                array_search($membership->role, $staffRoles, true),
                mb_strtolower($membership->user->name),
                $membership->id,
            ]);

        $invitations = $studio->invitations()
            ->pending()
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        $timezone = config()->string('studio.timezone');

        return Inertia::render('studios/team', [
            'members' => $members->values()->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->label(),
                'removable' => $membership->role !== StudioRole::Owner,
            ]),
            'invitations' => $invitations->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->label(),
                'expiresOn' => $invitation->expires_at->timezone($timezone)->translatedFormat('j \d\e F'),
                'expired' => $invitation->isExpired(),
            ]),
            'roles' => array_map(
                fn (StudioRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                StudioRole::invitable(),
            ),
        ]);
    }
}
