<?php

namespace App\Policies;

use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudioPolicy
{
    /**
     * Anyone can create a studio, but a person owns a single one: Cupo has
     * no branches.
     */
    public function create(User $user): bool
    {
        return ! $user->memberships()->where('role', StudioRole::Owner)->exists();
    }

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
