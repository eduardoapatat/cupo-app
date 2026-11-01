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
 * Light, dark or the system's, from an icon at the top right. The icon is
 * the one chosen (sun, moon or screen), so «Sistema» shows as such even
 * when the system is dark.
 */
export default function AppearanceMenu() {
    const { appearance, updateAppearance } = useAppearance();
    const current =
        OPTIONS.find((option) => option.value === appearance) ?? OPTIONS[2];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    aria-label={`Apariencia: ${current.label}`}
                >
                    <current.icon aria-hidden="true" />
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
