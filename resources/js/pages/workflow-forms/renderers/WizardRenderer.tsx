import { Link } from '@inertiajs/react';
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
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    show as workflowFormShow,
    submit as workflowFormSubmit,
} from '@/routes/workflow-forms';
import { normalizeChoices, parseSelectOptions } from '../form-helpers';
import type { FormField, ProgressPayload, Step } from '../types';
import { WorkflowFormRendererHeader } from '../WorkflowFormRendererHeader';
import type { StepRendererProps } from './types';

const CHOICE_ICON_MAP: Record<string, LucideIcon> = {
    ScrollText,
    GraduationCap,
    FilePenLine,
    ArrowRightLeft,
};

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

export function WizardRenderer({
    token,
    step,
    run_id,
    progress,
    previous_token,
    form,
    hasChoiceCards,
    interactionMode,
    onInteractionModeChange,
}: StepRendererProps) {
    return (
        <div className="flex min-h-0 min-w-0 w-full flex-1 flex-col bg-background">
            <WorkflowFormRendererHeader
                workflowName={progress.workflow_name}
                stepTitle={step.title}
                run_id={run_id}
                interactionMode={interactionMode}
                onInteractionModeChange={onInteractionModeChange}
            />
            <div className="scrollbar-discrete min-h-0 flex-1 overflow-y-auto">
                <div className="mx-auto w-full max-w-2xl space-y-6 px-4 py-6 lg:px-6">
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
        </div>
    );
}
