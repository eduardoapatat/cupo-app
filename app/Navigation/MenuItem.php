<?php

namespace App\Navigation;

use App\Enums\StudioPermission;
use App\Models\Studio;

/**
 * An item of the studio sidebar. Each role's menu and its order live in
 * StudioRole::menu(). Items whose page is not built yet have no route and
 * show as «Pronto» (see docs/roles-y-permisos.md).
 */
enum MenuItem: string
{
    case Home = 'home';
    case Agenda = 'agenda';
    case Students = 'students';
    case SellPlan = 'sell-plan';
    case Payments = 'payments';
    case CashRegister = 'cash-register';
    case Classes = 'classes';
    case Plans = 'plans';
    case Team = 'team';
    case Reports = 'reports';
    case StudioSettings = 'studio-settings';
    case MySessions = 'my-sessions';
    case Book = 'book';
    case MyBookings = 'my-bookings';
    case MyPlan = 'my-plan';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Inicio',
            self::Agenda => 'Agenda',
            self::Students => 'Alumnas',
            self::SellPlan => 'Vender plan',
            self::Payments => 'Pagos',
            self::CashRegister => 'Caja',
            self::Classes => 'Clases',
            self::Plans => 'Planes',
            self::Team => 'Equipo',
            self::Reports => 'Reportes',
            self::StudioSettings => 'Estudio',
            self::MySessions => 'Mis clases',
            self::Book => 'Reservar',
            self::MyBookings => 'Mis reservas',
            self::MyPlan => 'Mi plan',
        };
    }

    /**
     * The permission a role needs to have the item. The home only requires
     * being a member of the studio.
     */
    public function permission(): ?StudioPermission
    {
        return match ($this) {
            self::Home => null,
            self::Agenda => StudioPermission::ManageBookings,
            self::Students => StudioPermission::ManageStudents,
            self::SellPlan => StudioPermission::SellPlans,
            self::Payments => StudioPermission::ReviewPayments,
            self::CashRegister => StudioPermission::OperateCashRegister,
            self::Classes => StudioPermission::ManageSchedule,
            self::Plans => StudioPermission::ManagePlans,
            self::Team => StudioPermission::ManageStaff,
            self::Reports => StudioPermission::ViewReports,
            self::StudioSettings => StudioPermission::ManageStudio,
            self::MySessions => StudioPermission::ViewSessions,
            self::Book, self::MyBookings => StudioPermission::BookForSelf,
            self::MyPlan => StudioPermission::ViewOwnAccount,
        };
    }

    /**
     * The name of the item's route, or null while its page is not built.
     */
    public function routeName(): ?string
    {
        return match ($this) {
            self::Home => 'studios.home',
            self::Students => 'studios.students.index',
            self::Team => 'studios.team.index',
            self::StudioSettings => 'studios.settings.edit',
            default => null,
        };
    }

    /**
     * The item as the sidebar receives it, with its link in the studio.
     *
     * @return array{key: string, label: string, href: string|null}
     */
    public function toNavigation(Studio $studio): array
    {
        $routeName = $this->routeName();

        return [
            'key' => $this->value,
            'label' => $this->label(),
            'href' => $routeName === null ? null : route($routeName, $studio, absolute: false),
        ];
    }
}
