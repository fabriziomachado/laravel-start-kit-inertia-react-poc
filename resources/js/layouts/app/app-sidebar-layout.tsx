import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ImpersonatingBanner } from '@/components/impersonating-banner';
import { IMPERSONATION_BANNER_LAYOUT_OFFSET } from '@/lib/impersonation-layout';
import { cn } from '@/lib/utils';
import type { AppLayoutProps, SharedData } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { impersonating } = usePage<SharedData>().props;

    return (
        <div
            className={cn('flex min-h-screen flex-col')}
            style={
                impersonating
                    ? ({
                          '--impersonation-banner-offset':
                              IMPERSONATION_BANNER_LAYOUT_OFFSET,
                      } as CSSProperties)
                    : undefined
            }
        >
            <ImpersonatingBanner />
            <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                <AppShell variant="sidebar">
                    <AppSidebar />
                    <AppContent variant="sidebar" className="overflow-x-hidden">
                        <AppSidebarHeader breadcrumbs={breadcrumbs} />
                        {children}
                    </AppContent>
                </AppShell>
            </div>
        </div>
    );
}
