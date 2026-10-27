import type { Auth } from '@/types/auth';
import type { CurrentStudio } from '@/types/studio';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            studio: CurrentStudio | null;
            [key: string]: unknown;
        };
    }
}
