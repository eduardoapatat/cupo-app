import { Link } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ClipboardList,
    House,
    type LucideIcon,
    Receipt,
    Settings2,
    ShoppingBag,
    Ticket,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { CurrentStudio, MenuKey } from '@/types';

const ICONS: Record<MenuKey, LucideIcon> = {
    home: House,
    agenda: CalendarDays,
    students: Users,
    'sell-plan': ShoppingBag,
    payments: Receipt,
    'cash-register': Wallet,
    classes: CalendarClock,
    plans: Ticket,
    team: UserCog,
    reports: ChartColumn,
    'studio-settings': Settings2,
    'my-sessions': CalendarDays,
    book: CalendarPlus,
    'my-bookings': ClipboardList,
    'my-plan': Ticket,
};

/**
 * The studio sidebar: the menu of the member's role, as the server builds
 * it (StudioRole::menu()). Items without a link arrive in a later phase and
 * show as «Pronto».
 */
export function StudioNav({ studio }: { studio: CurrentStudio }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="truncate">
                {studio.name}
            </SidebarGroupLabel>
            <SidebarMenu>
                {studio.menu.map((item) => {
                    const Icon = ICONS[item.key];

                    return (
                        <SidebarMenuItem key={item.key}>
                            {item.href ? (
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.label }}
                                >
                                    <Link href={item.href} prefetch>
                                        <Icon />
                                        <span>{item.label}</span>
                                    </Link>
                                </SidebarMenuButton>
                            ) : (
                                <>
                                    <SidebarMenuButton
                                        disabled
                                        tooltip={{
                                            children: `${item.label} (pronto)`,
                                        }}
                                    >
                                        <Icon />
                                        <span>{item.label}</span>
                                    </SidebarMenuButton>
                                    <SidebarMenuBadge className="text-muted-foreground">
                                        Pronto
                                    </SidebarMenuBadge>
                                </>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
