import { Head, Link, useForm, type InertiaFormProps } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowRightLeft,
    Check,
    Clock,
    FilePenLine,
    GraduationCap,
    ListTree,
    MessageSquare,
    ScrollText,
    Sparkles,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    show as workflowFormShow,
    submit as workflowFormSubmit,
} from '@/routes/workflow-forms';
import type { BreadcrumbItem } from '@/types';

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

type ProgressSideTabId = 'details' | 'activities' | 'ai';

const PROGRESS_SIDE_TABS: {
    id: ProgressSideTabId;
    label: string;
    icon: LucideIcon;
}[] = [
    { id: 'details', label: 'Detalhes', icon: ListTree },
    { id: 'activities', label: 'Atividades', icon: MessageSquare },
    { id: 'ai', label: 'IA', icon: Sparkles },
];

const CHOICE_ICON_MAP: Record<string, LucideIcon> = {
    ScrollText,
    GraduationCap,
    FilePenLine,
    ArrowRightLeft,
};

type ChoiceCardDef = {
    value: string;
    label: string;
    description?: string;
    icon?: string;
};

type FormField = {
    key: string;
    label: string;
    type: string;
    required?: boolean;
    placeholder?: string;
    options?: string;
    choices?: ChoiceCardDef[];
};

type Step = {
    title: string;
    description?: string | null;
    submit_label: string;
    fields: FormField[];
};

type ProgressStep = {
    node_id: number;
    label: string;
    node_key: string;
    state: 'completed' | 'current' | 'pending';
    completed_at: string | null;
    summary_lines: string[];
    description?: string | null;
    actor_name?: string | null;
};

type ProgressPayload = {
    workflow_name: string;
    workflow_description?: string | null;
    steps: ProgressStep[];
};

type TimelineHeadingRow = { type: 'heading'; title: string; reactKey: string };
type TimelineStepRow = {
    type: 'step';
    step: ProgressStep;
    displayIndex: number;
    reactKey: string;
};

type Props = {
    token: string;
    step: Step;
    run_id: number;
    prefill: Record<string, unknown>;
    previous_token: string | null;
    progress: ProgressPayload;
};

function normalizeChoices(raw: unknown): ChoiceCardDef[] {
    if (!Array.isArray(raw)) {
        return [];
    }
    const out: ChoiceCardDef[] = [];
    for (const row of raw) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        const r = row as Record<string, unknown>;
        const value = r.value;
        const label = r.label;
        if (typeof value !== 'string' || typeof label !== 'string') {
            continue;
        }
        out.push({
            value,
            label,
            description:
                typeof r.description === 'string' ? r.description : undefined,
            icon: typeof r.icon === 'string' ? r.icon : undefined,
        });
    }

    return out;
}

function buildInitialData(
    fields: FormField[],
): Record<string, string | boolean | number> {
    const data: Record<string, string | boolean | number> = {};
    for (const f of fields) {
        if (f.type === 'boolean') {
            data[f.key] = false;
        } else if (f.type === 'number') {
            data[f.key] = '';
        } else {
            data[f.key] = '';
        }
    }

    return data;
}

function mergeInitialData(
    fields: FormField[],
    prefill: Record<string, unknown>,
): Record<string, string | boolean | number> {
    const data = buildInitialData(fields);
    for (const f of fields) {
        if (!Object.prototype.hasOwnProperty.call(prefill, f.key)) {
            continue;
        }
        const v = prefill[f.key];
        if (f.type === 'boolean') {
            data[f.key] = v === true || v === 1 || v === '1' || v === 'true';
        } else if (f.type === 'number') {
            if (typeof v === 'number' && !Number.isNaN(v)) {
                data[f.key] = v;
            } else if (typeof v === 'string' && v !== '') {
                data[f.key] = Number(v);
            }
        } else {
            data[f.key] = v == null ? '' : String(v);
        }
    }

    return data;
}

function parseSelectOptions(csv?: string): string[] {
    if (!csv) {
        return [];
    }

    return csv
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
}

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

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
        return 'Preencha o formulário à esquerda e utilize o botão de envio para avançar.';
    }

    return null;
}

function FormStepsSegmentBar({
    progress,
    step,
}: {
    progress: ProgressPayload;
    step: Step;
}) {
    const formSteps = useMemo(
        () => progress.steps.filter((s) => s.node_key === 'form_step'),
        [progress.steps],
    );
    const currentIdx = formSteps.findIndex((s) => s.state === 'current');
    const total = formSteps.length;

    if (total < 2 || currentIdx < 0) {
        return null;
    }

    const tag = step.title.replace(/\.$/, '').toUpperCase();

    return (
        <div className="space-y-2">
            <div className="flex gap-1" role="presentation">
                {Array.from({ length: total }, (_, i) => (
                    <div
                        key={i}
                        className={cn(
                            'h-1 min-w-0 flex-1 rounded-full transition-colors',
                            i <= currentIdx ? 'bg-foreground' : 'bg-muted',
                        )}
                    />
                ))}
            </div>
            <p className="text-[10px] font-medium tracking-wider text-muted-foreground">
                ETAPA {currentIdx + 1} DE {total} · {tag}
            </p>
        </div>
    );
}

function ChoiceCardsField({
    field,
    value,
    onChange,
    error,
}: {
    field: FormField;
    value: string;
    onChange: (v: string) => void;
    error?: string;
}) {
    const choices = normalizeChoices(field.choices);

    return (
        <fieldset className="grid gap-3">
            <legend className="sr-only">{field.label}</legend>
            <div
                className="grid gap-3"
                role="radiogroup"
                aria-label={field.label}
            >
                {choices.map((choice) => {
                    const selected = value === choice.value;
                    const Icon =
                        (choice.icon && CHOICE_ICON_MAP[choice.icon]) ||
                        ScrollText;

                    return (
                        <button
                            key={choice.value}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            onClick={() => {
                                onChange(choice.value);
                            }}
                            className={cn(
                                'flex w-full items-start gap-4 rounded-xl border-2 border-input p-4 text-left transition-colors',
                                'hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                selected
                                    ? 'border-foreground bg-muted/20'
                                    : 'border-border bg-background',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-12 shrink-0 items-center justify-center rounded-lg',
                                    selected
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-foreground',
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block font-semibold">
                                    {choice.label}
                                </span>
                                {choice.description ? (
                                    <span className="mt-0.5 block text-sm leading-snug text-muted-foreground">
                                        {choice.description}
                                    </span>
                                ) : null}
                            </span>
                            <span className="flex shrink-0 items-center pt-0.5">
                                {selected ? (
                                    <span className="flex size-7 items-center justify-center rounded-full bg-foreground text-background">
                                        <Check
                                            className="size-4"
                                            strokeWidth={2.5}
                                            aria-hidden
                                        />
                                    </span>
                                ) : (
                                    <span className="size-7 rounded-full border-2 border-dashed border-input opacity-40" />
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function ProgressPanel({
    progress,
    run_id,
}: {
    progress: ProgressPayload;
    run_id: number;
}) {
    const [scope, setScope] = useState<'all' | 'forms'>('all');
    const [progressTab, setProgressTab] =
        useState<ProgressSideTabId>('details');

    const formStepCount = useMemo(
        () => progress.steps.filter((s) => s.node_key === 'form_step').length,
        [progress.steps],
    );

    const showStepFilter = useMemo(
        () =>
            formStepCount > 0 &&
            progress.steps.some((s) => s.node_key !== 'form_step'),
        [formStepCount, progress.steps],
    );

    const displayedSteps = useMemo(() => {
        if (scope === 'forms') {
            return progress.steps.filter((s) => s.node_key === 'form_step');
        }

        return progress.steps;
    }, [progress.steps, scope]);

    const timelineRows = useMemo((): (
        | TimelineHeadingRow
        | TimelineStepRow
    )[] => {
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
    }, [displayedSteps]);

    const stepOnlyRows = useMemo(
        () =>
            timelineRows.filter((r): r is TimelineStepRow => r.type === 'step'),
        [timelineRows],
    );

    return (
        <aside
            className={cn(
                'flex h-full min-h-0 w-full min-w-0 shrink-0 flex-col border-t border-border bg-background',
                'lg:max-w-none lg:flex-row lg:border-t-0',
            )}
            aria-labelledby="workflow-progress-heading"
        >
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
                            <h2
                                id="workflow-progress-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
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

                        {showStepFilter ? (
                            <ToggleGroup
                                type="single"
                                value={scope}
                                onValueChange={(v) => {
                                    if (v === 'all' || v === 'forms') {
                                        setScope(v);
                                    }
                                }}
                                variant="outline"
                                size="sm"
                                className="grid w-full grid-cols-2 gap-0 shadow-none"
                            >
                                <ToggleGroupItem
                                    value="all"
                                    className="text-xs"
                                >
                                    Todas as etapas
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="forms"
                                    className="text-xs"
                                >
                                    Só formulários
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
                                const stepNo = String(
                                    row.displayIndex,
                                ).padStart(2, '0');
                                const totalShown = String(
                                    displayedSteps.length,
                                ).padStart(2, '0');
                                const stepIndexAmongSteps =
                                    stepOnlyRows.findIndex(
                                        (r) => r.step.node_id === s.node_id,
                                    );
                                const isLastStep =
                                    stepIndexAmongSteps >= 0 &&
                                    stepIndexAmongSteps ===
                                        stepOnlyRows.length - 1;

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
                                                    stepStatusDotClass(s.state),
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
                                                        s.state === 'pending' &&
                                                            'border-amber-200/80 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100',
                                                    )}
                                                >
                                                    {b.label}
                                                </Badge>
                                            </div>

                                            {(() => {
                                                const primary =
                                                    stepPrimaryDescription(s);
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
                {progressTab === 'ai' ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 px-2 py-12 text-center text-muted-foreground">
                        <Sparkles
                            className="size-10 text-muted-foreground/35"
                            strokeWidth={1.25}
                            aria-hidden
                        />
                        <p className="text-sm leading-relaxed">
                            Assistente de IA neste painel — em breve.
                        </p>
                    </div>
                ) : null}
            </div>

            <nav
                role="tablist"
                aria-label="Secções do painel de andamento"
                className="flex shrink-0 flex-row items-stretch justify-around gap-0.5 border-t border-border bg-background px-1.5 py-2 lg:w-[4.5rem] lg:flex-col lg:justify-start lg:gap-1 lg:border-t-0 lg:border-l lg:px-2 lg:py-3"
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
                                setProgressTab(tab.id);
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

type WorkflowFormPrimaryColumnProps = {
    token: string;
    step: Step;
    run_id: number;
    progress: ProgressPayload;
    previous_token: string | null;
    form: InertiaFormProps<Record<string, unknown>>;
    hasChoiceCards: boolean;
};

function WorkflowFormPrimaryColumn({
    token,
    step,
    run_id,
    progress,
    previous_token,
    form,
    hasChoiceCards,
}: WorkflowFormPrimaryColumnProps) {
    return (
        <div className="scrollbar-discrete flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-y-auto p-4 lg:px-8 lg:py-6">
            <div className="w-full max-w-none space-y-6">
                <FormStepsSegmentBar progress={progress} step={step} />
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {step.title}
                    </h1>
                    {step.description ? (
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {String(step.description)}
                        </p>
                    ) : null}
                    <p className="mt-1 text-xs text-muted-foreground">
                        Execução #{run_id}
                    </p>
                </div>

                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(workflowFormSubmit.url(token));
                    }}
                >
                    {step.fields.map((field) => (
                        <div
                            key={field.key}
                            className={cn(
                                'grid gap-2',
                                field.type === 'choice_cards' && 'gap-3',
                            )}
                        >
                            {field.type === 'choice_cards' ? null : (
                                <Label htmlFor={field.key}>{field.label}</Label>
                            )}
                            {field.type === 'choice_cards' ? (
                                <ChoiceCardsField
                                    field={{
                                        ...field,
                                        choices: normalizeChoices(
                                            field.choices,
                                        ),
                                    }}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(v) => form.setData(field.key, v)}
                                    error={form.errors[field.key]}
                                />
                            ) : null}
                            {field.type === 'textarea' ? (
                                <textarea
                                    id={field.key}
                                    name={field.key}
                                    required={Boolean(field.required)}
                                    placeholder={field.placeholder}
                                    className={cn(
                                        'flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground md:text-sm',
                                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    )}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                />
                            ) : null}
                            {field.type === 'string' ||
                            field.type === 'email' ? (
                                <Input
                                    id={field.key}
                                    name={field.key}
                                    type={
                                        field.type === 'email'
                                            ? 'email'
                                            : 'text'
                                    }
                                    required={Boolean(field.required)}
                                    placeholder={field.placeholder}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                />
                            ) : null}
                            {field.type === 'number' ? (
                                <Input
                                    id={field.key}
                                    name={field.key}
                                    type="number"
                                    required={Boolean(field.required)}
                                    placeholder={field.placeholder}
                                    value={
                                        form.data[field.key] === ''
                                            ? ''
                                            : String(form.data[field.key] ?? '')
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            field.key,
                                            e.target.value === ''
                                                ? ''
                                                : Number(e.target.value),
                                        )
                                    }
                                />
                            ) : null}
                            {field.type === 'boolean' ? (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={field.key}
                                        checked={Boolean(form.data[field.key])}
                                        onCheckedChange={(v) =>
                                            form.setData(field.key, v === true)
                                        }
                                    />
                                </div>
                            ) : null}
                            {field.type === 'select' ? (
                                <select
                                    id={field.key}
                                    name={field.key}
                                    required={Boolean(field.required)}
                                    className={cn(
                                        'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    )}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                >
                                    <option value="">—</option>
                                    {parseSelectOptions(field.options).map(
                                        (opt) => (
                                            <option key={opt} value={opt}>
                                                {opt}
                                            </option>
                                        ),
                                    )}
                                </select>
                            ) : null}
                            {field.type === 'choice_cards' ? null : (
                                <InputError message={form.errors[field.key]} />
                            )}
                        </div>
                    ))}

                    <div
                        className={cn(
                            'flex flex-wrap items-center gap-3 pt-1',
                            previous_token
                                ? 'w-full justify-between'
                                : 'w-full justify-end',
                        )}
                    >
                        {previous_token ? (
                            <Button variant="outline" type="button" asChild>
                                <Link
                                    href={workflowFormShow.url(previous_token)}
                                    prefetch={false}
                                >
                                    Voltar
                                </Link>
                            </Button>
                        ) : null}
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-w-[8rem] gap-2"
                        >
                            {form.processing && <Spinner />}
                            {step.submit_label}
                            {hasChoiceCards ? (
                                <ArrowRight className="size-4" aria-hidden />
                            ) : null}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function WorkflowFormShow(props: Props) {
    return <WorkflowFormShowInner key={props.token} {...props} />;
}

function WorkflowFormShowInner({
    token,
    step,
    run_id,
    prefill,
    previous_token,
    progress,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: dashboard() },
            { title: step.title, href: '#' },
        ],
        [step.title],
    );

    const form = useForm(mergeInitialData(step.fields, prefill));
    const hasChoiceCards = step.fields.some((f) => f.type === 'choice_cards');

    const [lgUp, setLgUp] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.matchMedia('(min-width: 1024px)').matches,
    );

    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1024px)');
        const fn = () => {
            setLgUp(mq.matches);
        };
        fn();
        mq.addEventListener('change', fn);

        return () => {
            mq.removeEventListener('change', fn);
        };
    }, []);

    const layoutPersistence = useDefaultLayout({
        id: 'workflow-form-resize',
        panelIds: ['workflow-form', 'workflow-progress'],
        storage: ssrSafeLocalStorage,
    });

    const formColumnProps: WorkflowFormPrimaryColumnProps = {
        token,
        step,
        run_id,
        progress,
        previous_token,
        form: form as InertiaFormProps<Record<string, unknown>>,
        hasChoiceCards,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={step.title} />
            <div className="flex min-h-0 flex-1 flex-col lg:max-h-[calc(100svh-4rem-var(--impersonation-banner-offset,0px))] lg:min-h-0">
                {lgUp ? (
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
                            <WorkflowFormPrimaryColumn {...formColumnProps} />
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
                            <ProgressPanel
                                progress={progress}
                                run_id={run_id}
                            />
                        </ResizablePanel>
                    </ResizablePanelGroup>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col">
                        <WorkflowFormPrimaryColumn {...formColumnProps} />
                        <ProgressPanel progress={progress} run_id={run_id} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
