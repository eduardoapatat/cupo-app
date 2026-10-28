<?php

namespace App\Navigation;

use App\Enums\StudioPermission;

/**
 * A block of the studio's Inicio. Each role's blocks and their order live
 * in StudioRole::homeBlocks(); the page holds their text. Blocks whose data
 * arrives in a later phase show as «Pronto» (see docs/roles-y-permisos.md).
 */
enum HomeBlock: string
{
    case TodayOccupancy = 'today-occupancy';
    case Alerts = 'alerts';
    case MonthIncome = 'month-income';
    case TodaySessions = 'today-sessions';
    case Arrivals = 'arrivals';
    case ReceiptsToReview = 'receipts-to-review';
    case CashRegister = 'cash-register';
    case TeachingToday = 'teaching-today';
    case NextBooking = 'next-booking';
    case ClassesLeft = 'classes-left';
    case PlanExpiry = 'plan-expiry';

    /**
     * The permission whose data the block shows.
     */
    public function permission(): StudioPermission
    {
        return match ($this) {
            self::TodayOccupancy, self::TodaySessions, self::Arrivals => StudioPermission::ManageBookings,
            self::Alerts => StudioPermission::ReviewPayments,
            self::MonthIncome => StudioPermission::ViewReports,
            self::ReceiptsToReview => StudioPermission::ReviewPayments,
            self::CashRegister => StudioPermission::OperateCashRegister,
            self::TeachingToday => StudioPermission::ViewSessions,
            self::NextBooking => StudioPermission::BookForSelf,
            self::ClassesLeft, self::PlanExpiry => StudioPermission::ViewOwnAccount,
        };
    }

    /**
     * Whether the block shows real data already. The rest arrive with plans,
     * payments and the cash register (phase 3).
     */
    public function isBuilt(): bool
    {
        return in_array($this, [self::TodayOccupancy, self::TodaySessions, self::Arrivals, self::TeachingToday, self::NextBooking], true);
    }
}
