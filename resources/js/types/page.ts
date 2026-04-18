import type { Auth, User } from '@/types/auth';

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    impersonating: boolean;
    impersonator: User | null;
    flows_error?: string | null;
    flows_success?: string | null;
};
