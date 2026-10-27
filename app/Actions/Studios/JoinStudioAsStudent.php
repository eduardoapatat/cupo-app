<?php

namespace App\Actions\Studios;

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;

class JoinStudioAsStudent
{
    /**
     * Add the user to the studio as a student, as the enrollment link does.
     * Someone already in the studio keeps their membership and role.
     */
    public function handle(Studio $studio, User $user): Membership
    {
        return $studio->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => StudioRole::Student],
        );
    }
}
