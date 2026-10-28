<?php

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClassSessionPolicy
{
    /**
     * «Only her own» for attendance: whoever teaches the class marks it,
     * and so does whoever manages the studio's bookings (the front desk and
     * the owner), never by role. The session is already scoped to the
     * studio in the URL, so another studio's answers 404 before this.
     */
    public function markAttendance(User $user, ClassSession $session): Response
    {
        $studio = $session->studio;

        if (! $user->can('attendance.mark', $studio)) {
            return Response::deny();
        }

        return $session->instructor_id === $user->id || $user->can('bookings.manage', $studio)
            ? Response::allow()
            : Response::deny();
    }
}
