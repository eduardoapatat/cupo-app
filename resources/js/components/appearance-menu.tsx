import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const OPTIONS: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Claro' },
    { value: 'dark', icon: Moon, label: 'Oscuro' },
    { value: 'system', icon: Monitor, label: 'Sistema' },
];

/**
 * Light, dark or the system's, from an icon at the top right: the sun in
 * light mode, the moon in dark mode.
 */
export default function AppearanceMenu() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    aria-label="Apariencia"
                >
                    <Sun className="dark:hidden" aria-hidden="true" />
                    <Moon className="hidden dark:block" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {OPTIONS.map(({ value, icon: Icon, label }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => updateAppearance(value)}
                        aria-current={appearance === value ? 'true' : undefined}
                    >
                        <Icon aria-hidden="true" />
                        {label}
                        {appearance === value && (
                            <Check className="ml-auto" aria-hidden="true" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
