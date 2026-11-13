import type { Auth } from '@/types/auth';
import type { Notifications, PushSettings } from '@/types/ui';
import type Echo from '@ably/laravel-echo';
import type * as Ably from 'ably';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare global {
    interface Window {
        Ably: typeof Ably;
        Echo: Echo;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            notifications: Notifications;
            push: PushSettings;
            sidebarOpen: boolean;
            locale: string;
            [key: string]: unknown;
        };
    }
}
