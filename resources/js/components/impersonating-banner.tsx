import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { leave } from '@/routes/impersonate';
import type { SharedData } from '@/types';

export function ImpersonatingBanner() {
    const { impersonating, impersonator, auth } = usePage<SharedData>().props;

    if (!impersonating || !impersonator || !auth.user) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex min-h-[var(--impersonation-banner-offset)] flex-col',
                'border-b border-amber-500/25 bg-gradient-to-r from-amber-500/15 via-amber-400/10 to-amber-500/15',
                'text-amber-950 dark:border-amber-400/20 dark:from-amber-950/40 dark:via-amber-900/30 dark:to-amber-950/40 dark:text-amber-50',
            )}
            data-test="impersonating-banner"
        >
            <div className="relative flex min-h-0 flex-1 items-center justify-center px-12 py-2 sm:px-16 sm:py-2.5">
                <p className="text-center text-xs leading-snug sm:text-sm">
                    Você está logado como{' '}
                    <span className="font-semibold">{auth.user.name}</span>
                </p>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="absolute top-1/2 right-1 h-7 -translate-y-1/2 gap-1 border-amber-800/15 bg-white/90 px-2 text-xs text-amber-950 shadow-sm hover:bg-white sm:right-2 dark:border-amber-200/15 dark:bg-amber-950/50 dark:text-amber-50 dark:hover:bg-amber-900/60"
                    data-test="impersonating-leave"
                    onClick={() => {
                        router.get(leave.url());
                    }}
                >
                    <LogOut className="size-3.5 shrink-0" />
                    Sair
                </Button>
            </div>
        </div>
    );
}
