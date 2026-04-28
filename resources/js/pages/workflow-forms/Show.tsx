import { Head, router, useForm, usePage, type InertiaFormProps } from '@inertiajs/react';
import { Clock, ListTree, MessageSquare, ScrollText, Sparkles, type LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types/page';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Separator } from '@/components/ui/separator';
import { Drawer, DrawerClose, DrawerContent } from '@/components/ui/drawer';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { mergeInitialData } from './form-helpers';
import { AiCopilotTab } from './progress/AiCopilotTab';
import { ChatbotRenderer } from './renderers/ChatbotRenderer';
import type { StepRendererProps } from './renderers/types';
import { WizardRenderer } from './renderers/WizardRenderer';
import type {
    ChatMessage,
    ProgressPayload,
    ProgressSideTabId,
    ProgressStep,
    Step,
    TimelineHeadingRow,
    TimelineStepRow,
} from './types';

const MIN_PROGRESS_PANEL_PX = 280;

const MAX_PROGRESS_PANEL_PX = 720;

const ssrSafeLocalStorage = {
    getItem: (key: string): string | null => {
        if (typeof window === 'undefined') {
            return null;
        }

        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    setItem: (key: string, value: string): void => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(key, value);
        } catch {
            /* ignore */
        }
    },
} as const;

const PROGRESS_SIDE_TABS: {
    id: ProgressSideTabId;
    label: string;
    icon: LucideIcon;
}[] = [
    { id: 'details', label: 'Detalhes', icon: ListTree },
    { id: 'activities', label: 'Atividades', icon: MessageSquare },
    { id: 'copilot', label: 'Assistente IA', icon: Sparkles },
];

type Props = {
    token: string;
    step: Step;
    run_id: number;
    prefill: Record<string, unknown>;
    previous_token: string | null;
    progress: ProgressPayload;
    preferences: { workflow_form_renderer: 'wizard' | 'chatbot' };
    conversation: { id: number; messages: ChatMessage[] };
    workflow_form_copilot_available: boolean;
};

function formatTimeOnly(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(iso));
    } catch {
        return '—';
    }
}

function formatShortDate(iso: string | null): string {
    if (!iso) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(iso));
    } catch {
        return '';
    }
}

/** Data e hora numa linha para a lista compacta do painel de andamento. */
function formatCompactStepTimestamp(
    state: ProgressStep['state'],
    completedAt: string | null,
): string {
    if (state === 'current') {
        return 'Agora';
    }
    if (state === 'pending') {
        return '—';
    }
    if (!completedAt) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(completedAt));
    } catch {
        return '—';
    }
}

/** Cabeçalho de grupo estilo agenda (ex.: seg., 17 de abr.) */
function formatDateGroupHeading(iso: string): string {
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }).format(new Date(iso));
    } catch {
        return formatShortDate(iso);
    }
}

function calendarDayKey(iso: string): string {
    const d = new Date(iso);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function stepSectionKey(s: ProgressStep): string {
    if (s.state === 'pending') {
        return '__pending__';
    }
    if (s.state === 'current') {
        return '__current__';
    }
    if (s.completed_at) {
        return `day:${calendarDayKey(s.completed_at)}`;
    }

    return '__other__';
}

function stepSectionTitle(key: string, sampleIso: string | null): string {
    if (key === '__pending__') {
        return 'Pendentes';
    }
    if (key === '__current__') {
        return 'Em curso';
    }
    if (key === '__other__') {
        return 'Etapas';
    }
    if (key.startsWith('day:') && sampleIso) {
        return formatDateGroupHeading(sampleIso);
    }

    return 'Etapas';
}

function initialsFromLabel(label: string): string {
    const compact = label.replace(/[^\p{L}\d]/gu, '').slice(0, 2);
    if (compact.length >= 2) {
        return compact.toUpperCase();
    }

    return (
        label
            .split(/\s+/)
            .filter(Boolean)
            .map((w) => w[0])
            .join('')
            .slice(0, 2)
            .toUpperCase() || '—'
    );
}

function stateBadge(state: ProgressStep['state']): {
    label: string;
    variant: 'default' | 'secondary' | 'outline';
} {
    if (state === 'completed') {
        return { label: 'Concluída', variant: 'outline' };
    }
    if (state === 'current') {
        return { label: 'Em curso', variant: 'default' };
    }

    return { label: 'Pendente', variant: 'outline' };
}

function stepStatusDotClass(state: ProgressStep['state']): string {
    if (state === 'completed') {
        return 'bg-emerald-500';
    }
    if (state === 'current') {
        return 'bg-sky-500';
    }

    return 'bg-amber-500';
}

function stepPrimaryDescription(s: ProgressStep): string | null {
    const d = s.description?.trim();
    if (s.state === 'pending') {
        return (
            d ||
            'Esta etapa será executada depois de concluir as etapas anteriores.'
        );
    }

    return d || null;
}

function stepSecondaryHint(s: ProgressStep): string | null {
    if (s.state === 'current') {
        return 'Preencha o formulário e utilize o botão de envio para avançar.';
    }

    return null;
}

function ProgressPanel({
    progress,
    run_id,
    formToken,
    copilotAvailable,
    progressTab,
    onProgressTabChange,
    variant = 'sidebar',
}: {
    progress: ProgressPayload;
    run_id: number;
    formToken: string;
    copilotAvailable: boolean;
    progressTab: ProgressSideTabId | null;
    onProgressTabChange: (tab: ProgressSideTabId | null) => void;
    variant?: 'sidebar' | 'drawer';
}) {
    const [timelineView, setTimelineView] = useState<'complete' | 'compact'>(
        'complete',
    );
    const collapsed = progressTab === null;
    const inDrawer = variant === 'drawer';

    const showTimelineViewToggle = progress.steps.length >= 2;

    const displayedSteps = progress.steps;

    const timelineRows = useMemo((): (
        | TimelineHeadingRow
        | TimelineStepRow
    )[] => {
        if (timelineView === 'compact') {
            return displayedSteps.map((step, index) => ({
                type: 'step' as const,
                step,
                displayIndex: index + 1,
                reactKey: `step-${step.node_id}`,
            }));
        }

        const out: (TimelineHeadingRow | TimelineStepRow)[] = [];
        let prevSectionKey: string | null = null;

        displayedSteps.forEach((step, index) => {
            const sectionKey = stepSectionKey(step);
            if (sectionKey !== prevSectionKey) {
                out.push({
                    type: 'heading',
                    title: stepSectionTitle(sectionKey, step.completed_at),
                    reactKey: `heading-${sectionKey}-${index}`,
                });
                prevSectionKey = sectionKey;
            }
            out.push({
                type: 'step',
                step,
                displayIndex: index + 1,
                reactKey: `step-${step.node_id}`,
            });
        });

        return out;
    }, [displayedSteps, timelineView]);

    const stepOnlyRows = useMemo(
        () =>
            timelineRows.filter((r): r is TimelineStepRow => r.type === 'step'),
        [timelineRows],
    );

    return (
        <aside
            className={cn(
                inDrawer
                    ? 'flex h-full min-h-0 w-full min-w-0 shrink-0 flex-col bg-background'
                    : collapsed
                        ? 'flex h-full min-h-0 min-w-0 shrink-0 flex-col border-t border-border bg-background lg:w-[4.5rem] lg:border-t-0 lg:border-l lg:border-border'
                        : 'flex h-full min-h-0 w-full min-w-0 shrink-0 flex-col border-t border-border bg-background lg:max-w-none lg:flex-row lg:border-t-0 lg:border-l lg:border-border',
            )}
            aria-label="Painel de andamento"
        >
            {progressTab ? (
                <div
                    role="tabpanel"
                    id={`progress-tabpanel-${progressTab}`}
                    aria-labelledby={`progress-tab-${progressTab}`}
                    className="scrollbar-discrete flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-4 lg:min-w-0 lg:p-5"
                >
                    {progressTab === 'details' ? (
                        <>
                            <header className="space-y-1">
                                <p className="font-mono text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                    Execução #{run_id}
                                </p>
                                <h2 className="text-lg font-semibold tracking-tight">
                                    Andamento
                                </h2>
                                <p className="text-sm leading-snug text-muted-foreground">
                                    {progress.workflow_name}
                                </p>
                                {progress.workflow_description ? (
                                    <p className="pt-0.5 text-xs leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                        {progress.workflow_description}
                                    </p>
                                ) : null}
                            </header>

                            {showTimelineViewToggle ? (
                                <ToggleGroup
                                    type="single"
                                    value={timelineView}
                                    onValueChange={(v) => {
                                        if (v === 'complete' || v === 'compact') {
                                            setTimelineView(v);
                                        }
                                    }}
                                    variant="outline"
                                    size="sm"
                                    className="grid w-full grid-cols-2 gap-0 shadow-none"
                                >
                                    <ToggleGroupItem
                                        value="complete"
                                        className="text-xs"
                                    >
                                        Completo
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="compact"
                                        className="text-xs"
                                    >
                                        Compacto
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            ) : null}

                            <Separator />

                            <div className="flex flex-col pb-2" role="list">
                                {timelineRows.map((row, rowIdx) => {
                                    if (row.type === 'heading') {
                                        return (
                                            <div
                                                key={row.reactKey}
                                                className={cn(
                                                    'pb-2 text-xs font-medium tracking-wide text-muted-foreground',
                                                    rowIdx > 0 && 'pt-8',
                                                )}
                                            >
                                                {row.title}
                                            </div>
                                        );
                                    }

                                    const s = row.step;
                                    const b = stateBadge(s.state);
                                    const stepIndexAmongSteps =
                                        stepOnlyRows.findIndex(
                                            (r) => r.step.node_id === s.node_id,
                                        );
                                    const isLastStep =
                                        stepIndexAmongSteps >= 0 &&
                                        stepIndexAmongSteps ===
                                            stepOnlyRows.length - 1;

                                    if (timelineView === 'compact') {
                                        return (
                                            <div
                                                key={row.reactKey}
                                                role="listitem"
                                                className={cn(
                                                    'flex min-w-0 items-center gap-3 py-2',
                                                    !isLastStep &&
                                                        'border-b border-border/60',
                                                )}
                                            >
                                                <span className="w-[9.25rem] shrink-0 font-mono text-[11px] leading-tight tabular-nums text-muted-foreground">
                                                    {formatCompactStepTimestamp(
                                                        s.state,
                                                        s.completed_at,
                                                    )}
                                                </span>
                                                <p className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                                                    {s.label}
                                                </p>
                                                <Badge
                                                    variant={b.variant}
                                                    className={cn(
                                                        'shrink-0 rounded-full px-2.5 py-0 text-[10px] font-medium uppercase',
                                                        s.state ===
                                                            'completed' &&
                                                            'border-emerald-500/40 bg-emerald-500/12 text-emerald-900 dark:border-emerald-400/35 dark:bg-emerald-500/15 dark:text-emerald-100',
                                                        s.state === 'pending' &&
                                                            'border-amber-200/80 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100',
                                                    )}
                                                >
                                                    {b.label}
                                                </Badge>
                                            </div>
                                        );
                                    }

                                    const stepNo = String(
                                        row.displayIndex,
                                    ).padStart(2, '0');
                                    const totalShown = String(
                                        displayedSteps.length,
                                    ).padStart(2, '0');

                                    return (
                                        <div
                                            key={row.reactKey}
                                            className="flex items-stretch gap-0"
                                            role="listitem"
                                        >
                                            <div className="w-[4.5rem] shrink-0 pt-0.5 text-right font-mono text-muted-foreground tabular-nums">
                                                {s.state === 'completed' &&
                                                s.completed_at ? (
                                                    <>
                                                        <p className="text-base leading-none font-semibold tracking-tight text-foreground">
                                                            {formatTimeOnly(
                                                                s.completed_at,
                                                            )}
                                                        </p>
                                                        <p className="mt-1 text-xs leading-none opacity-80">
                                                            {formatShortDate(
                                                                s.completed_at,
                                                            )}
                                                        </p>
                                                        <p className="mt-2 flex items-center justify-end gap-1 text-[10px] leading-none opacity-80">
                                                            <Clock
                                                                className="size-3 shrink-0 opacity-70"
                                                                aria-hidden
                                                            />
                                                            <span>
                                                                Etapa {stepNo}/
                                                                {totalShown}
                                                            </span>
                                                        </p>
                                                    </>
                                                ) : null}
                                                {s.state === 'current' ? (
                                                    <>
                                                        <p className="text-base leading-none font-semibold text-foreground">
                                                            •
                                                        </p>
                                                        <p className="mt-1 text-xs leading-none opacity-80">
                                                            Agora
                                                        </p>
                                                        <p className="mt-2 flex items-center justify-end gap-1 text-[10px] leading-none opacity-80">
                                                            <Clock
                                                                className="size-3 shrink-0 opacity-70"
                                                                aria-hidden
                                                            />
                                                            <span>
                                                                Etapa {stepNo}/
                                                                {totalShown}
                                                            </span>
                                                        </p>
                                                    </>
                                                ) : null}
                                                {s.state === 'pending' ? (
                                                    <>
                                                        <p className="text-base leading-none font-semibold tracking-tight text-foreground opacity-35">
                                                            —
                                                        </p>
                                                        <p className="mt-1 text-xs leading-none opacity-70">
                                                            A seguir
                                                        </p>
                                                        <p className="mt-2 flex items-center justify-end gap-1 text-[10px] leading-none opacity-80">
                                                            <Clock
                                                                className="size-3 shrink-0 opacity-70"
                                                                aria-hidden
                                                            />
                                                            <span>
                                                                Etapa {stepNo}/
                                                                {totalShown}
                                                            </span>
                                                        </p>
                                                    </>
                                                ) : null}
                                            </div>

                                            <div className="relative flex w-6 shrink-0 flex-col items-center self-stretch pt-1">
                                                {!isLastStep ? (
                                                    <div
                                                        className="absolute top-[13px] bottom-0 left-1/2 w-px -translate-x-1/2 bg-border"
                                                        aria-hidden
                                                    />
                                                ) : null}
                                                <span
                                                    className={cn(
                                                        'relative z-[1] size-2.5 shrink-0 rounded-full border-2 border-background',
                                                        stepStatusDotClass(
                                                            s.state,
                                                        ),
                                                    )}
                                                    title={b.label}
                                                />
                                            </div>

                                            <div
                                                className={cn(
                                                    'min-w-0 flex-1 space-y-2',
                                                    !isLastStep && 'pb-10',
                                                )}
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                                    <p className="leading-snug font-semibold text-foreground">
                                                        {s.label}
                                                    </p>
                                                    <Badge
                                                        variant={b.variant}
                                                        className={cn(
                                                            'shrink-0 rounded-full px-2.5 py-0 text-[10px] font-medium uppercase',
                                                            s.state ===
                                                                'completed' &&
                                                                'border-emerald-500/40 bg-emerald-500/12 text-emerald-900 dark:border-emerald-400/35 dark:bg-emerald-500/15 dark:text-emerald-100',
                                                            s.state ===
                                                                'pending' &&
                                                                'border-amber-200/80 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100',
                                                        )}
                                                    >
                                                        {b.label}
                                                    </Badge>
                                                </div>

                                                {(() => {
                                                    const primary =
                                                        stepPrimaryDescription(
                                                            s,
                                                        );
                                                    const hint =
                                                        stepSecondaryHint(s);
                                                    if (!primary && !hint) {
                                                        return null;
                                                    }

                                                    return (
                                                        <div className="text-xs leading-relaxed text-muted-foreground">
                                                            <p className="flex items-start gap-1.5">
                                                                <ScrollText
                                                                    className="mt-0.5 size-3.5 shrink-0 text-muted-foreground/70"
                                                                    aria-hidden
                                                                />
                                                                <span className="min-w-0 space-y-2">
                                                                    {primary ? (
                                                                        <span className="block whitespace-pre-wrap">
                                                                            {
                                                                                primary
                                                                            }
                                                                        </span>
                                                                    ) : null}
                                                                    {hint ? (
                                                                        <span className="block whitespace-pre-wrap">
                                                                            {hint}
                                                                        </span>
                                                                    ) : null}
                                                                </span>
                                                            </p>
                                                        </div>
                                                    );
                                                })()}

                                                {s.summary_lines.length > 0 ? (
                                                    <ul className="space-y-1 text-xs leading-relaxed text-muted-foreground">
                                                        {s.summary_lines.map(
                                                            (line, li) => (
                                                                <li key={li}>
                                                                    {line}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                ) : null}

                                                <div className="flex min-w-0 items-center gap-2.5 pt-2">
                                                    <Avatar className="size-7 shrink-0 border-0 shadow-none">
                                                        <AvatarFallback className="bg-muted/50 text-[10px] font-semibold text-muted-foreground">
                                                            {initialsFromLabel(
                                                                s.actor_name?.trim() ||
                                                                    s.label,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    {s.actor_name?.trim() ? (
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-[10px] leading-none font-normal text-muted-foreground">
                                                                {s.actor_name.trim()}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    ) : null}
                    {progressTab === 'activities' ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 px-2 py-12 text-center text-muted-foreground">
                            <MessageSquare
                                className="size-10 text-muted-foreground/35"
                                strokeWidth={1.25}
                                aria-hidden
                            />
                            <p className="text-sm leading-relaxed">
                                Atividades do processo estarão disponíveis em breve.
                            </p>
                        </div>
                    ) : null}
                    {progressTab === 'copilot' ? (
                        <AiCopilotTab
                            token={formToken}
                            available={copilotAvailable}
                        />
                    ) : null}
                </div>
            ) : null}

            <nav
                role="tablist"
                aria-label="Secções do painel de andamento"
                className={cn(
                    'flex shrink-0 flex-row items-stretch justify-around gap-0.5 border-t border-border bg-background px-1.5 py-2',
                    'lg:w-[4.5rem] lg:flex-col lg:justify-start lg:gap-1 lg:px-2 lg:py-3',
                    inDrawer
                        ? 'lg:border-0'
                        : collapsed
                            ? 'lg:border-l-0 lg:border-t-0'
                            : 'lg:border-t-0 lg:border-l',
                )}
            >
                {PROGRESS_SIDE_TABS.map((tab) => {
                    const TabIcon = tab.icon;
                    const selected = progressTab === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            id={`progress-tab-${tab.id}`}
                            aria-selected={selected}
                            aria-controls={`progress-tabpanel-${tab.id}`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => {
                                onProgressTabChange(selected ? null : tab.id);
                            }}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-1 rounded-lg px-1 py-2 text-[10px] font-medium transition-colors lg:flex-none lg:px-0.5 lg:py-2',
                                selected
                                    ? 'text-violet-700 dark:text-violet-200'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                            )}
                        >
                            <span
                                className={cn(
                                    'grid size-9 shrink-0 place-items-center rounded-lg transition-colors',
                                    selected
                                        ? 'bg-violet-100 dark:bg-violet-900/45'
                                        : 'bg-transparent text-muted-foreground',
                                )}
                            >
                                <TabIcon
                                    className="size-[18px] stroke-[1.5]"
                                    aria-hidden
                                />
                            </span>
                            <span className="max-w-[4.25rem] text-center leading-tight">
                                {tab.label}
                            </span>
                        </button>
                    );
                })}
            </nav>
        </aside>
    );
}

export type ChatAdvancePayload = {
    token: string;
    step: Step;
    run_id: number;
    prefill: Record<string, unknown>;
    previous_token: string | null;
    progress: ProgressPayload;
    conversation: { id: number; messages: ChatMessage[] };
};

export default function WorkflowFormShow(props: Props) {
    return <WorkflowFormShowInner {...props} />;
}

function WorkflowFormShowInner({
    token,
    step,
    run_id,
    prefill,
    previous_token,
    progress,
    preferences,
    conversation,
    workflow_form_copilot_available,
}: Props) {
    const { auth } = usePage<SharedData>().props;

    const [activeToken, setActiveToken] = useState(token);
    const [activeStep, setActiveStep] = useState(step);
    const [activeRunId, setActiveRunId] = useState(run_id);
    const [activePrefill, setActivePrefill] = useState(prefill);
    const [activePreviousToken, setActivePreviousToken] = useState(previous_token);
    const [activeProgress, setActiveProgress] = useState(progress);
    const [activeConversation, setActiveConversation] = useState(conversation);

    /** Incrementado em cada avanço pelo chat; o renderer usa para anexar o segmento sem confundir com navegação Inertia (Strict Mode incluído). */
    const [chatAdvanceSeq, setChatAdvanceSeq] = useState(0);

    useEffect(() => {
        setActiveToken(token);
        setActiveStep(step);
        setActiveRunId(run_id);
        setActivePrefill(prefill);
        setActivePreviousToken(previous_token);
        setActiveProgress(progress);
        setActiveConversation(conversation);
        form.setData(mergeInitialData(step.fields, prefill));
        // Só quando o token da página (Inertia) muda — não repetir quando o chat atualiza estado local.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- props alinhadas com `token`
    }, [token]);

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: dashboard() },
            { title: activeStep.title, href: '#' },
        ],
        [activeStep.title],
    );

    const form = useForm(mergeInitialData(activeStep.fields, activePrefill));
    const hasChoiceCards = activeStep.fields.some((f) => f.type === 'choice_cards');

    const [renderer, setRenderer] = useState<
        'wizard' | 'chatbot'
    >(preferences.workflow_form_renderer);

    useEffect(() => {
        setRenderer(preferences.workflow_form_renderer);
    }, [preferences.workflow_form_renderer, activeToken]);

    const layoutPersistence = useDefaultLayout({
        id: 'workflow-form-resize',
        panelIds: ['workflow-form', 'workflow-progress'],
        storage: ssrSafeLocalStorage,
    });

    const [progressTab, setProgressTab] = useState<ProgressSideTabId | null>(
        'details',
    );
    const [progressSheetOpen, setProgressSheetOpen] = useState(false);

    const handleChatDraftUpdate = useCallback(
        (payload: { messages: ChatMessage[]; draftValues: Record<string, unknown> }) => {
            // Substituir só o bloco da etapa atual no cumulativo (o API devolve o segmento).
            setActiveConversation((prev) => {
                const rows = [...prev.messages] as { role: string; content?: unknown }[];
                let lastSys = -1;
                for (let i = rows.length - 1; i >= 0; i--) {
                    if (rows[i]?.role === 'system') {
                        lastSys = i;
                        break;
                    }
                }
                const head = lastSys >= 0 ? prev.messages.slice(0, lastSys + 1) : [];

                return { ...prev, messages: [...head, ...payload.messages] };
            });
            setActivePrefill((prev) => ({ ...prev, ...payload.draftValues }));
            for (const [k, v] of Object.entries(payload.draftValues)) {
                form.setData(k, v as never);
            }
        },
        [form],
    );

    const handleChatAdvance = useCallback(
        (next: ChatAdvancePayload) => {
            setChatAdvanceSeq((n) => n + 1);
            setActiveToken(next.token);
            setActiveStep(next.step);
            setActiveRunId(next.run_id);
            setActivePrefill(next.prefill);
            setActivePreviousToken(next.previous_token);
            setActiveProgress(next.progress);
            // Manter no pai o mesmo cumulativo que o chat mostra, para um eventual remount
            // não perder etapas anteriores (o JSON de avanço traz só o segmento da etapa nova).
            setActiveConversation((prev) => {
                const transition = {
                    role: 'system' as const,
                    content: next.step.title,
                    meta: { at: new Date().toISOString() },
                };
                return {
                    id: next.conversation.id,
                    messages: [
                        ...prev.messages,
                        transition,
                        ...next.conversation.messages,
                    ] as ChatMessage[],
                };
            });
            form.setData(mergeInitialData(next.step.fields, next.prefill));
            if (typeof window !== 'undefined' && window.history?.replaceState) {
                try {
                    window.history.replaceState(
                        window.history.state,
                        '',
                        `/workflow-forms/${next.token}`,
                    );
                } catch {
                    /* ignore */
                }
            }
        },
        [form],
    );

    const handleWorkflowComplete = useCallback(
        (redirectUrl: string) => {
            router.visit(redirectUrl);
        },
        [],
    );

    const wizardProps: StepRendererProps = {
        token: activeToken,
        step: activeStep,
        run_id: activeRunId,
        progress: activeProgress,
        previous_token: activePreviousToken,
        form: form as InertiaFormProps<Record<string, unknown>>,
        hasChoiceCards,
        interactionMode: renderer,
        onInteractionModeChange: setRenderer,
    };

    const primaryColumn = (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <div className="relative min-h-0 min-w-0 flex-1">
                {/*
                  Manter wizard e chat montados: o chat acumula histórico entre
                  etapas em estado local; desmontar ao alternar apagava a conversa.
                */}
                <div
                    className={cn(
                        'absolute inset-0 flex min-h-0 min-w-0 flex-col overflow-hidden',
                        renderer === 'wizard'
                            ? 'z-10'
                            : 'pointer-events-none invisible z-0',
                    )}
                    aria-hidden={renderer !== 'wizard'}
                >
                    <WizardRenderer key={activeToken} {...wizardProps} />
                </div>
                <div
                    className={cn(
                        'absolute inset-0 flex min-h-0 min-w-0 flex-col overflow-hidden',
                        renderer === 'chatbot'
                            ? 'z-10'
                            : 'pointer-events-none invisible z-0',
                    )}
                    aria-hidden={renderer !== 'chatbot'}
                >
                    <ChatbotRenderer
                        token={activeToken}
                        step={activeStep}
                        run_id={activeRunId}
                        previous_token={activePreviousToken}
                        prefill={activePrefill}
                        initialMessages={activeConversation.messages}
                        user={auth.user}
                        workflowName={activeProgress?.workflow_name ?? null}
                        onAdvance={handleChatAdvance}
                        onComplete={handleWorkflowComplete}
                        onDraftUpdate={handleChatDraftUpdate}
                        chatAdvanceSeq={chatAdvanceSeq}
                        interactionMode={renderer}
                        onInteractionModeChange={setRenderer}
                    />
                </div>
            </div>
        </div>
    );

    const progressPanel = (
        <ProgressPanel
            progress={activeProgress}
            run_id={activeRunId}
            formToken={activeToken}
            copilotAvailable={workflow_form_copilot_available}
            progressTab={progressTab}
            onProgressTabChange={(tab) => {
                setProgressTab(tab);
                if (tab === null) {
                    setProgressSheetOpen(false);
                }
            }}
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={activeStep.title} />
            {/*
              Em viewport `lg+`, formulário e andamento em painéis redimensionáveis com
              layout persistido. Abaixo de `lg`, colunas empilhadas; ao cruzar o breakpoint
              a árvore muda (grupo vs stack), mas wizard + chat permanecem montados no pai
              com histórico em `activeConversation`.
            */}
            <div className="flex min-h-0 flex-1 flex-col lg:max-h-[calc(100svh-4rem-var(--impersonation-banner-offset,0px))] lg:min-h-0">
                <div className="hidden min-h-0 flex-1 lg:flex">
                    {progressTab ? (
                        <ResizablePanelGroup
                            orientation="horizontal"
                            className="flex min-h-0 flex-1"
                            defaultLayout={layoutPersistence.defaultLayout}
                            onLayoutChanged={layoutPersistence.onLayoutChanged}
                        >
                            <ResizablePanel
                                id="workflow-form"
                                className="flex min-h-0 min-w-0"
                                minSize="24%"
                            >
                                {primaryColumn}
                            </ResizablePanel>
                            <ResizableHandle
                                withHandle
                                className="bg-border/60"
                                aria-label="Redimensionar painel de andamento"
                            />
                            <ResizablePanel
                                id="workflow-progress"
                                className="flex min-h-0 min-w-0"
                                minSize={`${MIN_PROGRESS_PANEL_PX}px`}
                                maxSize={`${MAX_PROGRESS_PANEL_PX}px`}
                            >
                                {progressPanel}
                            </ResizablePanel>
                        </ResizablePanelGroup>
                    ) : (
                        <div className="flex min-h-0 flex-1">
                            {primaryColumn}
                            {progressPanel}
                        </div>
                    )}
                </div>

                <div className="flex min-h-0 flex-1 flex-col lg:hidden">
                    {primaryColumn}
                    <Drawer
                        open={progressSheetOpen}
                        onOpenChange={(open) => {
                            setProgressSheetOpen(open);
                            if (open && progressTab === null) {
                                setProgressTab('details');
                            }
                        }}
                        dismissible
                        snapPoints={[0.35, 0.85]}
                        activeSnapPoint={0.85}
                    >
                        <div className="pointer-events-none fixed right-4 bottom-4 z-40 flex items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                className="pointer-events-auto gap-2 rounded-full shadow-lg"
                                onClick={() => {
                                    setProgressSheetOpen(true);
                                    if (progressTab === null) {
                                        setProgressTab('details');
                                    }
                                }}
                            >
                                <ListTree className="size-4" aria-hidden />
                                Andamento
                            </Button>
                        </div>
                        <DrawerContent className="h-[85svh] p-0">
                            <div className="flex h-full min-h-0 flex-col">
                                <div className="relative shrink-0">
                                    <div className="mx-auto mt-3 h-1.5 w-16 rounded-full bg-muted-foreground/25" />
                                    <DrawerClose asChild>
                                        <button
                                            type="button"
                                            className="absolute top-3 right-3 inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/50 hover:text-foreground"
                                            aria-label="Fechar"
                                        >
                                            <span className="text-xl leading-none">
                                                ×
                                            </span>
                                        </button>
                                    </DrawerClose>
                                </div>
                                <div className="min-h-0 flex-1">
                                    <ProgressPanel
                                        progress={activeProgress}
                                        run_id={activeRunId}
                                        formToken={activeToken}
                                        copilotAvailable={
                                            workflow_form_copilot_available
                                        }
                                        progressTab={progressTab}
                                        onProgressTabChange={(tab) => {
                                            setProgressTab(tab);
                                            if (tab === null) {
                                                setProgressSheetOpen(false);
                                            }
                                        }}
                                        variant="drawer"
                                    />
                                </div>
                            </div>
                        </DrawerContent>
                    </Drawer>
                </div>
            </div>
        </AppLayout>
    );
}
