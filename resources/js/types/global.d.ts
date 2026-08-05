import type { Auth } from '@/types/auth';
import type { Tenancy } from '@/types/tenancy';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            tenancy: Tenancy;
            flash: {
                success?: string | null;
                error?: string | null;
                duplicates?:
                    | { id: number; name: string; similarity: number }[]
                    | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
