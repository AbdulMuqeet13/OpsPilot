import type { Auth } from '@/types/auth';
import type { Brand, Sandbox } from '@/types/brand';
import type { Toast } from '@/types';

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
            brand: Brand;
            sandbox: Sandbox | null;
            aiPaused?: string | null;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: Toast;
            submittedReference?: string;
            newApiKey?: string;
            leadSubmitted?: boolean;
        };
    }
}
