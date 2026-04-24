import { LayoutList, MessagesSquare } from 'lucide-react';
import { useCallback, useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { patchJson } from '@/lib/workflow-form-api';
import { cn } from '@/lib/utils';
import { preferences as preferencesRoute } from '@/routes/workflow-forms';

type Renderer = 'wizard' | 'chatbot';

const WIZARD_HINT =
    'Formulário — todos os campos visíveis em passos, ideal para revisão rápida.';
const CHAT_HINT =
    'Chat — conversa guiada, uma pergunta de cada vez com o assistente.';

export function RendererModeToggle({
    value,
    onChange,
    disabled,
    className,
}: {
    value: Renderer;
    onChange: (v: Renderer) => void;
    disabled?: boolean;
    className?: string;
}) {
    const [pending, setPending] = useState(false);

    const select = useCallback(
        async (next: Renderer) => {
            if (disabled || pending || next === value) {
                return;
            }
            setPending(true);
            try {
                const res = await patchJson<{
                    preferences: { workflow_form_renderer: Renderer };
                }>(preferencesRoute.url(), { workflow_form_renderer: next });
                if (res.ok) {
                    onChange(res.data.preferences.workflow_form_renderer);
                }
            } finally {
                setPending(false);
            }
        },
        [disabled, onChange, pending, value],
    );

    const busy = Boolean(disabled || pending);

    return (
        <div
            role="radiogroup"
            aria-label="Modo de preenchimento"
            className={cn(
                'inline-flex h-9 shrink-0 items-center rounded-lg border border-border/80 bg-muted/40 p-0.5 shadow-none',
                className,
            )}
        >
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        role="radio"
                        aria-checked={value === 'wizard'}
                        disabled={busy}
                        onClick={() => void select('wizard')}
                        className={cn(
                            'inline-flex size-8 items-center justify-center rounded-md transition-[color,box-shadow,background]',
                            'outline-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            'disabled:pointer-events-none disabled:opacity-50',
                            value === 'wizard'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )}
                    >
                        <LayoutList className="size-4" aria-hidden />
                        <span className="sr-only">Modo formulário</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-[14rem] text-left">
                    {WIZARD_HINT}
                </TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        role="radio"
                        aria-checked={value === 'chatbot'}
                        disabled={busy}
                        onClick={() => void select('chatbot')}
                        className={cn(
                            'inline-flex size-8 items-center justify-center rounded-md transition-[color,box-shadow,background]',
                            'outline-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            'disabled:pointer-events-none disabled:opacity-50',
                            value === 'chatbot'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )}
                    >
                        <MessagesSquare className="size-4" aria-hidden />
                        <span className="sr-only">Modo chat</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-[14rem] text-left">
                    {CHAT_HINT}
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
