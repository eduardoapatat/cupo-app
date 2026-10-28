<?php

namespace App\Enums;

/**
 * Who takes the place: a student, or someone of the team the front desk
 * added to the session. Both take a spot; only students will use a plan.
 */
enum BookingKind: string
{
    case Student = 'student';
    case Staff = 'staff';
}
