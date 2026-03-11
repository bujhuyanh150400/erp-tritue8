import type { Auth } from '@/lib/types/auth';
import type { ToastData } from '@/lib/types/utils';



declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash: {
                success: ToastData | null;
                error: ToastData | null;
                warning: ToastData | null;
                info: ToastData | null;
            };
            [key: string]: unknown;
        };
    }
}
