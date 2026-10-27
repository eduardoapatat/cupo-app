<?php

namespace App\Enums;

/**
 * One case per row of the permission matrix in docs/roles-y-permisos.md.
 * Each case is also the name of a Gate that takes the studio as argument.
 */
enum StudioPermission: string
{
    // Business decisions.
    case ManagePlans = 'plans.manage';
    case ManageSchedule = 'schedule.manage';
    case ManageRules = 'rules.manage';
    case ViewReports = 'reports.view';
    case ManageStudio = 'studio.manage';

    // Staff.
    case ManageStaff = 'staff.manage';

    // Students.
    case ManageStudents = 'students.manage';
    case ViewStudentContact = 'students.view_contact';

    // Payments.
    case SellPlans = 'plans.sell';
    case DiscountPlans = 'plans.discount';
    case ReviewPayments = 'payments.review';
    case VoidOpenPayments = 'payments.void_open';
    case VoidClosedPayments = 'payments.void_closed';
    case OperateCashRegister = 'cash.operate';

    // Sessions.
    case ManageBookings = 'bookings.manage';
    case OverrideBookingRules = 'bookings.override';
    case SubstituteInstructor = 'sessions.substitute';
    case CancelSessions = 'sessions.cancel';
    case ViewSessions = 'sessions.view';
    case MarkAttendance = 'attendance.mark';

    // Self-service, for students only.
    case BuyPlanOnline = 'self.buy_plan';
    case BookForSelf = 'self.book';
    case ViewOwnAccount = 'self.view';
}
