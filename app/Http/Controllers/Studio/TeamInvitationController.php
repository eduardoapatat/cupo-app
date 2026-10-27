<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Team\InviteToStudio;
use App\Actions\Team\SendInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\InviteToStudioRequest;
use App\Models\Invitation;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * The owner's invitations to her team, from «Equipo». An invitation of
 * another studio answers 404 (scoped bindings), and an accepted one too:
 * there is nothing left to resend or cancel.
 */
class TeamInvitationController extends Controller
{
    #[Authorize('staff.manage', 'studio')]
    public function store(InviteToStudioRequest $request, Studio $studio, #[CurrentUser] User $user, InviteToStudio $inviteToStudio): RedirectResponse
    {
        $invitation = $inviteToStudio->handle($studio, $user, $request->invitedEmail(), $request->invitedRole());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent the invitation to :email.', ['email' => $invitation->email])]);

        return to_route('studios.team.index', $studio);
    }

    #[Authorize('staff.manage', 'studio')]
    public function resend(Studio $studio, Invitation $invitation, SendInvitation $sendInvitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 404);

        $sendInvitation->handle($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent the invitation to :email again.', ['email' => $invitation->email])]);

        return to_route('studios.team.index', $studio);
    }

    #[Authorize('staff.manage', 'studio')]
    public function destroy(Studio $studio, Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->isAccepted(), 404);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('We cancelled the invitation to :email.', ['email' => $invitation->email])]);

        return to_route('studios.team.index', $studio);
    }
}
