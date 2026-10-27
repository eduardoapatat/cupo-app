/** A role in a studio (App\Enums\StudioRole). */
export type StudioRole = 'owner' | 'front_desk' | 'instructor' | 'student';

/** An item of the studio sidebar (App\Navigation\MenuItem). */
export type MenuKey =
    | 'home'
    | 'agenda'
    | 'students'
    | 'sell-plan'
    | 'payments'
    | 'cash-register'
    | 'classes'
    | 'plans'
    | 'team'
    | 'reports'
    | 'studio-settings'
    | 'my-sessions'
    | 'book'
    | 'my-bookings'
    | 'my-plan';

/** A menu item; without a link it arrives in a later phase («Pronto»). */
export type StudioMenuItem = {
    key: MenuKey;
    label: string;
    href: string | null;
};

/** The studio the sidebar shows, shared by HandleInertiaRequests. */
export type CurrentStudio = {
    name: string;
    slug: string;
    role: StudioRole;
    roleLabel: string;
    menu: StudioMenuItem[];
};

/** A choice of a select input, such as App\Enums\Commune::options(). */
export type Option = {
    value: string;
    label: string;
};

/** One of the user's studios, for the studio switcher. */
export type StudioLink = {
    name: string;
    slug: string;
    roleLabel: string;
    href: string;
};
