import { CalendarCheck } from 'lucide-react';
import type { LucideProps } from 'lucide-react';

/**
 * A neutral mark while Cupo has no logo of its own (PRODUCT.md: no brand
 * assets exist yet). It is a stroke icon, so it takes the text color.
 */
export default function AppLogoIcon(props: LucideProps) {
    return <CalendarCheck aria-hidden="true" {...props} />;
}
