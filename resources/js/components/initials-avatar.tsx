import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * A person's initials in a gray circle, the same avatar as the account
 * menu. Cupo keeps no photos (data minimization), so the name is all it
 * shows; the name itself sits next to it, hence hidden from screen readers.
 */
export default function InitialsAvatar({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn('size-8 overflow-hidden rounded-full', className)}
            aria-hidden="true"
        >
            <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
