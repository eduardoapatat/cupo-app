<?php

namespace App\Actions\Team;

use App\Enums\StudioRole;
use App\Models\Invitation;
use App\Models\Studio;
use App\Models\User;

class InviteToStudio
{
    public function __construct(private SendInvitation $sendInvitation) {}

    /**
     * Invite someone to the studio's team by email and send them the link.
     */
    public function handle(Studio $studio, User $invitedBy, string $email, StudioRole $role): Invitation
    {
        $invitation = $studio->invitations()->make([
            'email' => $email,
            'role' => $role,
            'expires_at' => now()->addDays(config()->integer('studio.invitation_days')),
        ]);
        $invitation->invitedBy()->associate($invitedBy);
        $invitation->save();

        $this->sendInvitation->handle($invitation);

        return $invitation;
    }
}
