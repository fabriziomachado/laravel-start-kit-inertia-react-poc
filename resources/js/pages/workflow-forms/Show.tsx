import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowRightLeft,
    Check,
    FilePenLine,
    GraduationCap,
    ScrollText,
    type LucideIcon,
} from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as workflowFormShow, submit as workflowFormSubmit } from '@/routes/workflow-forms';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

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
};

type ProgressPayload = {
    workflow_name: string;
    steps: ProgressStep[];
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
            description: typeof r.description === 'string' ? r.description : undefined,
            icon: typeof r.icon === 'string' ? r.icon : undefined,
        });
    }

    return out;
}

function buildInitialData(fields: FormField[]): Record<string, string | boolean | number> {
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

function mergeInitialData(fields: FormField[], prefill: Record<string, unknown>): Record<string, string | boolean | number> {
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

    return csv.split(',').map((s) => s.trim()).filter(Boolean);
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

function stateBadge(state: ProgressStep['state']): { label: string; variant: 'default' | 'secondary' | 'outline' } {
    if (state === 'completed') {
        return { label: 'Concluída', variant: 'secondary' };
    }
    if (state === 'current') {
        return { label: 'Em curso', variant: 'default' };
    }

    return { label: 'Pendente', variant: 'outline' };
}

function FormStepsSegmentBar({ progress, step }: { progress: ProgressPayload; step: Step }) {
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
            <p className="text-muted-foreground text-[10px] font-medium tracking-wider">
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
            <div className="grid gap-3" role="radiogroup" aria-label={field.label}>
                {choices.map((choice) => {
                    const selected = value === choice.value;
                    const Icon = (choice.icon && CHOICE_ICON_MAP[choice.icon]) || ScrollText;

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
                                'border-input flex w-full items-start gap-4 rounded-xl border-2 p-4 text-left transition-colors',
                                'hover:bg-muted/40 focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
                                selected ? 'border-foreground bg-muted/20' : 'border-border bg-background',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-12 shrink-0 items-center justify-center rounded-lg',
                                    selected ? 'bg-foreground text-background' : 'bg-muted text-foreground',
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block font-semibold">{choice.label}</span>
                                {choice.description ? (
                                    <span className="text-muted-foreground mt-0.5 block text-sm leading-snug">
                                        {choice.description}
                                    </span>
                                ) : null}
                            </span>
                            <span className="flex shrink-0 items-center pt-0.5">
                                {selected ? (
                                    <span className="bg-foreground flex size-7 items-center justify-center rounded-full text-background">
                                        <Check className="size-4" strokeWidth={2.5} aria-hidden />
                                    </span>
                                ) : (
                                    <span className="border-input size-7 rounded-full border-2 border-dashed opacity-40" />
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
    return (
        <aside
            className={cn(
                'border-border bg-muted/30 flex min-h-0 w-full shrink-0 flex-col border-t lg:w-[min(26rem,38vw)] lg:max-w-md lg:border-t-0 lg:border-l',
            )}
            aria-labelledby="workflow-progress-heading"
        >
            <div className="flex min-h-0 min-w-0 flex-1 flex-col gap-3 overflow-y-auto p-4 lg:p-5">
                <div>
                    <h2 id="workflow-progress-heading" className="text-lg font-semibold tracking-tight">
                        Andamento do processo
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm leading-snug">{progress.workflow_name}</p>
                    <p className="text-muted-foreground mt-2 text-xs">
                        Execução #{run_id} — etapas e dados já enviados (quando aplicável).
                    </p>
                </div>
                <Separator />
                <ol className="flex flex-col gap-4 pb-2">
                    {progress.steps.map((s, idx) => {
                        const b = stateBadge(s.state);

                        return (
                            <li key={s.node_id}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {idx + 1}/{progress.steps.length}
                                    </span>
                                    <Badge variant={b.variant}>{b.label}</Badge>
                                </div>
                                <p className="mt-1 font-medium">{s.label}</p>
                                {s.completed_at ? (
                                    <p className="text-muted-foreground text-xs">
                                        Concluída em {formatWhen(s.completed_at)}
                                    </p>
                                ) : null}
                                {s.state === 'current' ? (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        É nesta etapa que se encontra — preencha o formulário e avance.
                                    </p>
                                ) : null}
                                {s.summary_lines.length > 0 ? (
                                    <ul className="border-border bg-background/80 mt-2 rounded-md border px-3 py-2 text-xs">
                                        {s.summary_lines.map((line, li) => (
                                            <li key={li} className="py-0.5">
                                                {line}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                                {idx < progress.steps.length - 1 ? <Separator className="mt-4" /> : null}
                            </li>
                        );
                    })}
                </ol>
            </div>
        </aside>
    );
}

export default function WorkflowFormShow(props: Props) {
    return <WorkflowFormShowInner key={props.token} {...props} />;
}

function WorkflowFormShowInner({ token, step, run_id, prefill, previous_token, progress }: Props) {
    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: dashboard() },
            { title: step.title, href: '#' },
        ],
        [step.title],
    );

    const form = useForm(mergeInitialData(step.fields, prefill));
    const hasChoiceCards = step.fields.some((f) => f.type === 'choice_cards');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={step.title} />
            <div className="flex min-h-0 flex-1 flex-col lg:max-h-[calc(100svh-4rem-var(--impersonation-banner-offset,0px))] lg:flex-row">
                <div className="flex min-h-0 min-w-0 w-full flex-1 flex-col overflow-y-auto p-4 lg:px-8 lg:py-6">
                    <div className="w-full max-w-none space-y-6">
                        <FormStepsSegmentBar progress={progress} step={step} />
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">{step.title}</h1>
                            {step.description ? (
                                <p className="text-muted-foreground mt-2 text-sm whitespace-pre-wrap">
                                    {String(step.description)}
                                </p>
                            ) : null}
                            <p className="text-muted-foreground mt-1 text-xs">Execução #{run_id}</p>
                        </div>

                        <form
                            className="space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(workflowFormSubmit.url(token));
                            }}
                        >
                            {step.fields.map((field) => (
                                <div key={field.key} className={cn('grid gap-2', field.type === 'choice_cards' && 'gap-3')}>
                                    {field.type === 'choice_cards' ? null : <Label htmlFor={field.key}>{field.label}</Label>}
                                    {field.type === 'choice_cards' ? (
                                        <ChoiceCardsField
                                            field={{ ...field, choices: normalizeChoices(field.choices) }}
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
                                                'border-input placeholder:text-muted-foreground flex min-h-[100px] w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                            )}
                                            value={String(form.data[field.key] ?? '')}
                                            onChange={(e) => form.setData(field.key, e.target.value)}
                                        />
                                    ) : null}
                                    {field.type === 'string' || field.type === 'email' ? (
                                        <Input
                                            id={field.key}
                                            name={field.key}
                                            type={field.type === 'email' ? 'email' : 'text'}
                                            required={Boolean(field.required)}
                                            placeholder={field.placeholder}
                                            value={String(form.data[field.key] ?? '')}
                                            onChange={(e) => form.setData(field.key, e.target.value)}
                                        />
                                    ) : null}
                                    {field.type === 'number' ? (
                                        <Input
                                            id={field.key}
                                            name={field.key}
                                            type="number"
                                            required={Boolean(field.required)}
                                            placeholder={field.placeholder}
                                            value={form.data[field.key] === '' ? '' : String(form.data[field.key] ?? '')}
                                            onChange={(e) =>
                                                form.setData(field.key, e.target.value === '' ? '' : Number(e.target.value))
                                            }
                                        />
                                    ) : null}
                                    {field.type === 'boolean' ? (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={field.key}
                                                checked={Boolean(form.data[field.key])}
                                                onCheckedChange={(v) => form.setData(field.key, v === true)}
                                            />
                                        </div>
                                    ) : null}
                                    {field.type === 'select' ? (
                                        <select
                                            id={field.key}
                                            name={field.key}
                                            required={Boolean(field.required)}
                                            className={cn(
                                                'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                            )}
                                            value={String(form.data[field.key] ?? '')}
                                            onChange={(e) => form.setData(field.key, e.target.value)}
                                        >
                                            <option value="">—</option>
                                            {parseSelectOptions(field.options).map((opt) => (
                                                <option key={opt} value={opt}>
                                                    {opt}
                                                </option>
                                            ))}
                                        </select>
                                    ) : null}
                                    {field.type === 'choice_cards' ? null : <InputError message={form.errors[field.key]} />}
                                </div>
                            ))}

                            <div
                                className={cn(
                                    'flex flex-wrap items-center gap-3 pt-1',
                                    previous_token ? 'w-full justify-between' : 'w-full justify-end',
                                )}
                            >
                                {previous_token ? (
                                    <Button variant="outline" type="button" asChild>
                                        <Link href={workflowFormShow.url(previous_token)} prefetch={false}>
                                            Voltar
                                        </Link>
                                    </Button>
                                ) : null}
                                <Button type="submit" disabled={form.processing} className="gap-2 min-w-[8rem]">
                                    {form.processing && <Spinner />}
                                    {step.submit_label}
                                    {hasChoiceCards ? <ArrowRight className="size-4" aria-hidden /> : null}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <ProgressPanel progress={progress} run_id={run_id} />
            </div>
        </AppLayout>
    );
}
