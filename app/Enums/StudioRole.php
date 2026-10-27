<?php

namespace App\Enums;

/**
 * The role a person holds in one studio. It lives in the membership, never on
 * the user. Adding a role means adding a case here with its permissions (see
 * docs/roles-y-permisos.md).
 */
enum StudioRole: string
{
    case Owner = 'owner';
    case FrontDesk = 'front_desk';
    case Instructor = 'instructor';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dueña',
            self::FrontDesk => 'Recepción',
            self::Instructor => 'Instructor',
            self::Student => 'Alumna',
        };
    }

    /**
     * The role's column of the permission matrix. Instructors and students
     * act only on their own sessions and bookings; the policies of those
     * resources narrow it down (phases 2 and 3).
     *
     * @return list<StudioPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Everything but self-service, which is only for students.
            self::Owner => array_values(array_filter(
                StudioPermission::cases(),
                fn (StudioPermission $permission): bool => ! in_array($permission, self::Student->permissions(), true),
            )),
            self::FrontDesk => [
                StudioPermission::ManageStudents,
                StudioPermission::ViewStudentContact,
                StudioPermission::SellPlans,
                StudioPermission::ReviewPayments,
                StudioPermission::VoidOpenPayments,
                StudioPermission::OperateCashRegister,
                StudioPermission::ManageBookings,
                StudioPermission::SubstituteInstructor,
                StudioPermission::CancelSessions,
                StudioPermission::ViewSessions,
                StudioPermission::MarkAttendance,
            ],
            self::Instructor => [
                StudioPermission::ViewSessions,
                StudioPermission::MarkAttendance,
            ],
            self::Student => [
                StudioPermission::BuyPlanOnline,
                StudioPermission::BookForSelf,
                StudioPermission::ViewOwnAccount,
            ],
        };
    }

    public function allows(StudioPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
