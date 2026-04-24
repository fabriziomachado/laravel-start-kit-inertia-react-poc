import { Workflow } from 'lucide-react';
import { RendererModeToggle } from './RendererToggle';
import type { WorkflowInteractionMode } from './renderers/types';

export function WorkflowFormRendererHeader({
    workflowName,
    stepTitle,
    run_id,
    interactionMode,
    onInteractionModeChange,
}: {
    workflowName?: string | null;
    stepTitle: string;
    run_id: number;
    interactionMode: WorkflowInteractionMode;
    onInteractionModeChange: (mode: WorkflowInteractionMode) => void;
}) {
    return (
        <header className="shrink-0 border-b border-border/80 bg-background/90 px-4 py-3 backdrop-blur-sm supports-[backdrop-filter]:bg-background/75 lg:px-6">
            <div className="mx-auto flex max-w-2xl items-center gap-2 sm:gap-3">
                <span
                    className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-sm"
                    aria-hidden
                >
                    <Workflow className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h1 className="truncate text-sm font-semibold text-foreground">
                        {workflowName ?? stepTitle}
                    </h1>
                    <p className="truncate text-xs text-muted-foreground">
                        {workflowName ? `Etapa: ${stepTitle}` : 'Processo em curso'}
                    </p>
                </div>
                <RendererModeToggle
                    value={interactionMode}
                    onChange={onInteractionModeChange}
                />
                <span className="shrink-0 rounded-full border border-border bg-muted/40 px-2.5 py-1 font-mono text-[10px] leading-none text-muted-foreground tabular-nums">
                    #{run_id}
                </span>
            </div>
        </header>
    );
}
