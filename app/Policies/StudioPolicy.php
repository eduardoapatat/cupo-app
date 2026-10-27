<?php

namespace App\Policies;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudioPolicy
{
    /**
     * Only members enter a studio. Everyone else gets a 404, so they cannot
     * tell whether the studio exists.
     */
    public function view(User $user, Studio $studio): Response
    {
        return $user->roleIn($studio) !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
