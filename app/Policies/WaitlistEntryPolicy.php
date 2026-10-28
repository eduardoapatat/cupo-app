<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Auth\Access\Response;

class WaitlistEntryPolicy
{
    /**
     * A student leaves her own place in a waitlist only. Someone else's
     * answers 404, so she cannot tell it exists.
     */
    public function leaveOwn(User $user, WaitlistEntry $entry): Response
    {
        return $entry->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
