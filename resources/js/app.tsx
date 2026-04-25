import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';
import { AppToaster } from '@/components/app-toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('flash', (event) => {
    const flashToast = event.detail.flash.toast;
    if (!flashToast) {
        return;
    }

    const description =
        flashToast.description ?? flashToast.message ?? undefined;

    switch (flashToast.type) {
        case 'success':
            toast.success(flashToast.title, { description });
            break;
        case 'warning':
            toast.warning(flashToast.title, { description });
            break;
        case 'info':
            toast.info(flashToast.title, { description });
            break;
        case 'error':
        default:
            toast.error(flashToast.title, { description });
    }
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent<ComponentType>(
            `./pages/${name}.tsx`,
            import.meta.glob<ComponentType>('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <TooltipProvider delayDuration={0}>
                    <App {...props} />
                    <AppToaster />
                </TooltipProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
