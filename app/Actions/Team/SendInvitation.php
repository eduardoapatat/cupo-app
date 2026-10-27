<?php

namespace App\Actions\Team;

use App\Models\Invitation;
use App\Notifications\StudioInvitation;
use Illuminate\Support\Facades\Notification;

class SendInvitation
{
    /**
     * Give the invitation a fresh validity and email its link. Resending
     * uses it too, so a resent invitation always has the full time.
     */
    public function handle(Invitation $invitation): void
    {
        $invitation->update([
            'expires_at' => now()->addDays(config()->integer('studio.invitation_days')),
        ]);

        Notification::route('mail', $invitation->email)->notify(new StudioInvitation($invitation));
    }
}
