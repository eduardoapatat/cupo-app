<?php

namespace App\Actions\Team;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * Add the user to the studio with the invited role and close the
     * invitation. The invitation row is locked, so a double submit cannot
     * use it twice. Since the link arrived at that address, accepting it
     * also verifies the user's email.
     */
    public function handle(Invitation $invitation, User $user): Membership
    {
        return DB::transaction(function () use ($invitation, $user): Membership {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if ($invitation->isAccepted() || $invitation->isExpired()) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is no longer valid.'),
                ]);
            }

            $membership = $invitation->studio->memberships()->create([
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $membership;
        });
    }
}
