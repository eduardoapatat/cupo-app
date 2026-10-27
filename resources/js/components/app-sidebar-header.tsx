import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * The current studio and the person's role in it. The role belongs to the
 * studio, so both go together; with more than one studio, this is also the
 * switcher, and choosing one lands on its home.
 */
function StudioSwitcher() {
    const { studio, studios } = usePage().props;

    if (!studio) {
        return null;
    }

    const label = (
        <>
            <span
                aria-hidden="true"
                className="hidden max-w-48 truncate text-muted-foreground sm:block"
            >
                {studio.name}
            </span>
            <span className="sr-only">{studio.name}, tu rol:</span>
            <Badge variant="outline">{studio.roleLabel}</Badge>
        </>
    );

    if (studios.length < 2) {
        return (
            <p className="ml-auto flex shrink-0 items-center gap-2 text-sm">
                {label}
            </p>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="ml-auto shrink-0 gap-2 px-2 font-normal"
                    aria-label={`Cambiar de estudio. Estás en ${studio.name} como ${studio.roleLabel}`}
                >
                    {label}
                    <ChevronsUpDown className="text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Tus estudios
                </DropdownMenuLabel>
                {studios.map((item) => (
                    <DropdownMenuItem key={item.slug} asChild>
                        <Link
                            href={item.href}
                            className="w-full"
                            aria-current={
                                item.slug === studio.slug ? 'true' : undefined
                            }
                        >
                            <div className="grid min-w-0 flex-1 leading-tight">
                                <span className="truncate">{item.name}</span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {item.roleLabel}
                                </span>
                            </div>
                            {item.slug === studio.slug && (
                                <Check className="ml-auto" />
                            )}
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <StudioSwitcher />
        </header>
    );
}
