import type { CSSProperties } from 'react';
import { Toaster } from 'sonner';
import { cn } from '@/lib/utils';

/** Estilo do host `ol` do Sonner: central inferior sem depender de translateX (evita conflito com cascade). */
const sonnerHostStyle = {
    left: 0,
    right: 0,
    marginInline: 'auto',
    transform: 'none',
    ['--width']: 'min(100vw - 1.5rem, 24rem)',
} as CSSProperties;

/**
 * Sonner global: inferior central, visual alinhado ao showcase shadcn (cartão sólido, sombra suave, ação em bloco escuro).
 */
export function AppToaster() {
    return (
        <Toaster
            theme="system"
            position="bottom-center"
            offset={{
                bottom: 'max(1rem, env(safe-area-inset-bottom, 0px))',
            }}
            mobileOffset={{
                bottom: 'max(0.75rem, env(safe-area-inset-bottom, 0px))',
            }}
            gap={10}
            visibleToasts={4}
            expand={false}
            closeButton
            richColors={false}
            style={sonnerHostStyle}
            toastOptions={{
                classNames: {
                    toast: cn(
                        '!gap-4 !rounded-xl !border !p-4',
                        '!border-neutral-200/90 !bg-white !text-neutral-950',
                        'dark:!border-neutral-700 dark:!bg-neutral-950 dark:!text-neutral-50',
                        // Elevação discreta (tipo Sonner/shadcn showcase)
                        '!shadow-[0_4px_24px_-6px_rgba(0,0,0,0.12),0_2px_8px_-4px_rgba(0,0,0,0.06)]',
                        'dark:!shadow-[0_4px_24px_-6px_rgba(0,0,0,0.45),0_2px_8px_-4px_rgba(0,0,0,0.35)]',
                    ),
                    content: '!flex !flex-col !gap-0.5 !items-start !min-w-0 !flex-1',
                    title: cn(
                        '!text-[15px] !font-semibold !leading-snug !tracking-tight !text-neutral-950',
                        'dark:!text-neutral-50',
                    ),
                    description: cn(
                        '!mt-0 !text-[13px] !font-normal !leading-snug !text-neutral-500',
                        'dark:!text-neutral-400',
                    ),
                    actionButton: cn(
                        '!ml-auto !shrink-0 self-center',
                        '!rounded-md !border-0 !px-3.5 !py-2 !text-xs !font-medium',
                        '!bg-neutral-900 !text-white hover:!bg-neutral-800',
                        'dark:!bg-neutral-100 dark:!text-neutral-900 dark:hover:!bg-white',
                    ),
                    cancelButton: cn(
                        '!rounded-md !border !border-neutral-200 !bg-transparent !px-3 !py-1.5 !text-xs !font-medium !text-neutral-600',
                        'hover:!bg-neutral-100 dark:!border-neutral-600 dark:!text-neutral-300 dark:hover:!bg-neutral-800',
                    ),
                    closeButton: cn(
                        '!left-auto !right-2 !top-2 !size-6 !rounded-full !border !border-neutral-200 !bg-white !text-neutral-500',
                        'hover:!border-neutral-300 hover:!text-neutral-800',
                        'dark:!border-neutral-600 dark:!bg-neutral-900 dark:!text-neutral-400',
                        'dark:hover:!border-neutral-500 dark:hover:!text-neutral-200',
                    ),
                    error: cn(
                        '!border-red-200 !bg-red-50 !text-red-950',
                        'dark:!border-red-900/60 dark:!bg-red-950/40 dark:!text-red-50',
                    ),
                    success: cn(
                        '!border-emerald-200 !bg-emerald-50 !text-emerald-950',
                        'dark:!border-emerald-900/50 dark:!bg-emerald-950/35 dark:!text-emerald-50',
                    ),
                    warning: cn(
                        '!border-amber-200 !bg-amber-50 !text-amber-950',
                        'dark:!border-amber-900/50 dark:!bg-amber-950/35 dark:!text-amber-50',
                    ),
                    info: cn(
                        '!border-sky-200 !bg-sky-50 !text-sky-950',
                        'dark:!border-sky-900/50 dark:!bg-sky-950/35 dark:!text-sky-50',
                    ),
                },
            }}
        />
    );
}
