import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    ChevronDown,
    ExternalLink,
    FileText,
    Info,
    ListTree,
    LoaderCircle,
    MessageSquare,
    Search,
    Sparkles,
    UserSearch,
    type LucideIcon,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as flowsIndex, intake as flowsIntake } from '@/routes/flows';
import { store as flowsRunsStore } from '@/routes/flows/runs';
import { search as studentsSearch } from '@/routes/flows/intake/students';
import { store as negotiationsStore } from '@/routes/flows/intake/negotiations';
import { store as overridesStore } from '@/routes/flows/intake/overrides';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    query: string;
    requirements: Requirement[];
    popularRequirementIds: number[];
    requestedProcesses: RequestedProcess[];
};

type Requirement = {
    id: number;
    title: string;
    description: string | null;
    tags: string[];
    group: string;
};

type CatalogCategoryPill =
    | 'all'
    | 'popular'
    | 'academic'
    | 'financial'
    | 'scholarship';

function requirementHaystack(r: Requirement): string {
    return `${r.title} ${r.description ?? ''} ${r.tags.join(' ')} ${r.group}`.toLowerCase();
}

function requirementMatchesCatalogPill(
    r: Requirement,
    pill: CatalogCategoryPill,
    popularIds: readonly number[],
): boolean {
    if (pill === 'all') {
        return true;
    }
    if (pill === 'popular') {
        return new Set(popularIds).has(r.id);
    }

    const h = requirementHaystack(r);
    if (pill === 'academic') {
        return (
            h.includes('acad') ||
            h.includes('disciplin') ||
            h.includes('matrí') ||
            h.includes('matri') ||
            h.includes('tranc') ||
            h.includes('aproveit') ||
            h.includes('curri') ||
            h.includes('gradua')
        );
    }
    if (pill === 'financial') {
        return (
            h.includes('finan') ||
            h.includes('débit') ||
            h.includes('debit') ||
            h.includes('taxa') ||
            h.includes('pagamento') ||
            h.includes('divida') ||
            h.includes('dívida')
        );
    }
    if (pill === 'scholarship') {
        return h.includes('bolsa') || h.includes('scholar');
    }

    return true;
}

function requirementServiceGuidance(r: Requirement): {
    audience: string;
    feeLabel: string;
} {
    const h = requirementHaystack(r);
    let audience =
        'Alunos com vínculo ativo na instituição, em situação regular perante o regimento e as normas do curso.';
    if (
        h.includes('calour') ||
        h.includes('calouro') ||
        h.includes('ingress')
    ) {
        audience =
            'Calouros e estudantes no primeiro período letivo da graduação, incluindo ingressantes por vestibular, ENEM ou transferência externa quando o fluxo for equivalente.';
    }
    if (h.includes('bolsa') || h.includes('scholar')) {
        audience =
            'Estudantes que pretendem solicitar, manter ou renovar benefícios de bolsa, conforme editais e comissões competentes.';
    }
    if (
        h.includes('matrí') ||
        h.includes('matri') ||
        h.includes('tranc') ||
        h.includes('rematr')
    ) {
        audience =
            'Estudantes de graduação ou pós-graduação que precisam alterar o vínculo académico (matrícula, trancamento, rematrícula ou cancelamento de disciplinas).';
    }
    if (
        h.includes('finan') ||
        h.includes('débit') ||
        h.includes('debit') ||
        h.includes('pagamento') ||
        h.includes('dívida')
    ) {
        audience =
            'Alunos com débitos, parcelas em atraso ou que necessitam de documentos e acertos junto ao setor financeiro.';
    }

    let feeLabel =
        'Sem taxa fixa de abertura neste passo; custos específicos, se existirem, são informados nas etapas seguintes do fluxo ou na tabela de serviços.';
    if (
        h.includes('finan') ||
        h.includes('taxa') ||
        h.includes('pagamento') ||
        h.includes('dívida')
    ) {
        feeLabel =
            'Valores dependem da situação individual (parcelas, multas, descontos). Não comprometa valores ao aluno sem confirmar no financeiro ou no fluxo de cobrança.';
    }
    if (
        h.includes('certid') ||
        h.includes('declara') ||
        h.includes('histórico') ||
        h.includes('historico')
    ) {
        feeLabel =
            'Pode haver taxa por emissão, segunda via ou envio; consulte a tabela de serviços académicos e prazos de entrega.';
    }

    return { audience, feeLabel };
}

type RequestedProcess = {
    id: string;
    title: string;
    status: string;
    created_at: string;
};

type Student = {
    id: number;
    code: string;
    name: string;
    email: string;
    status: string;
    course: string;
    semester: string;
    unit: string;
    avatar_url: string | null;
    cpf?: string | null;
};

type Pendency = {
    id: string;
    type: 'financial' | 'academic' | string;
    summary: string;
    amount?: number;
};

type OverrideStatus = 'none' | 'requested' | 'approved';

type SearchResponse = {
    student: Student | null;
    pendencies: Pendency[];
    override_status: OverrideStatus;
};

type NegotiationArtifacts = {
    boleto_url: string;
    pix_qr_url: string;
    contrato_url: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Processos', href: flowsIndex() },
    { title: 'Novo processo', href: flowsIntake() },
];

const RECENT_REQUIREMENTS_KEY = 'flows.intake.recentRequirements.v1';

const CATALOG_PILL_OPTIONS: { id: CatalogCategoryPill; label: string }[] = [
    { id: 'all', label: 'Todos' },
    { id: 'popular', label: 'Mais utilizados' },
    { id: 'academic', label: 'Académico' },
    { id: 'financial', label: 'Financeiro' },
    { id: 'scholarship', label: 'Bolsas' },
];

function getCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    ).toString();
}

function readRecentRequirementIds(): number[] {
    try {
        const raw = localStorage.getItem(RECENT_REQUIREMENTS_KEY);
        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw) as unknown;
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .map((v) => Number(v))
            .filter((v) => Number.isFinite(v));
    } catch {
        return [];
    }
}

function writeRecentRequirementIds(ids: number[]) {
    localStorage.setItem(RECENT_REQUIREMENTS_KEY, JSON.stringify(ids));
}

function formatBrl(amount: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(amount);
}

function studentInitials(name: string): string {
    const parts = name
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[parts.length - 1][0] ?? ''}`.toUpperCase();
}

const MIN_FLOW_INTAKE_CONTEXT_PX = 280;

const MAX_FLOW_INTAKE_CONTEXT_PX = 720;

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

function IntakeScrollWrap({ children }: { children: ReactNode }) {
    return (
        <div className="scrollbar-discrete flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-y-auto p-4 lg:px-8 lg:py-6">
            <div className="flex flex-col gap-6">{children}</div>
        </div>
    );
}

type IntakeContextTabId = 'details' | 'service' | 'activities' | 'ai';

const INTAKE_CONTEXT_SIDE_TABS: {
    id: IntakeContextTabId;
    label: string;
    icon: LucideIcon;
}[] = [
    { id: 'details', label: 'Detalhes', icon: ListTree },
    { id: 'service', label: 'Guia', icon: FileText },
    { id: 'activities', label: 'Atividades', icon: MessageSquare },
    { id: 'ai', label: 'IA', icon: Sparkles },
];

function FlowIntakeStudentProfileCard({
    student,
    financialPendency,
    dossierUrl,
}: {
    student: Student;
    financialPendency: Pendency | undefined;
    dossierUrl: string | null;
}) {
    return (
        <div className="min-w-0 p-3 sm:p-3.5">
                <div className="flex items-start gap-3">
                    <Avatar className="size-12 shrink-0 rounded-lg ring-2 ring-background sm:size-14">
                        <AvatarImage
                            src={student.avatar_url ?? undefined}
                            alt=""
                        />
                        <AvatarFallback className="rounded-lg text-sm font-semibold">
                            {studentInitials(student.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1 pt-0.5">
                        <h3 className="text-sm font-semibold leading-snug tracking-tight text-foreground line-clamp-2 sm:text-[0.9375rem]">
                            {student.name}
                        </h3>
                        <div className="mt-1.5 flex flex-wrap gap-1">
                            {financialPendency &&
                            typeof financialPendency.amount === 'number' ? (
                                <Badge
                                    variant="destructive"
                                    className="h-5 max-w-full gap-0.5 px-1.5 py-0 text-[10px] font-medium leading-none"
                                >
                                    <Banknote
                                        className="size-2.5 shrink-0"
                                        aria-hidden
                                    />
                                    <span className="truncate">
                                        {formatBrl(financialPendency.amount)}
                                    </span>
                                </Badge>
                            ) : financialPendency ? (
                                <Badge
                                    variant="destructive"
                                    className="h-5 gap-0.5 px-1.5 py-0 text-[10px] font-medium leading-none"
                                >
                                    <Banknote
                                        className="size-2.5 shrink-0"
                                        aria-hidden
                                    />
                                    Financeiro
                                </Badge>
                            ) : null}
                            <Badge
                                variant="outline"
                                className={cn(
                                    'h-5 border px-1.5 py-0 text-[10px] font-medium leading-none',
                                    /ativo/i.test(student.status)
                                        ? 'border-chart-2/40 bg-chart-2/10 text-chart-2'
                                        : 'border-border/60 bg-muted/30 text-foreground',
                                )}
                            >
                                {student.status}
                            </Badge>
                        </div>
                    </div>
                </div>

                {dossierUrl ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-3 h-8 w-full gap-1.5 text-xs font-medium shadow-none"
                        asChild
                    >
                        <a href={dossierUrl} target="_blank" rel="noreferrer">
                            Ficha completa
                            <ExternalLink
                                className="size-3.5 shrink-0 opacity-70"
                                aria-hidden
                            />
                        </a>
                    </Button>
                ) : null}

                <dl className="mt-3 overflow-hidden rounded-lg border-0 bg-accent/35 dark:bg-accent/20">
                    <div className="divide-y divide-border/25 dark:divide-border/40">
                        <div className="px-2.5 py-2 sm:px-3 sm:py-2.5">
                            <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                Matrícula
                            </dt>
                            <dd className="mt-0.5 font-mono text-xs font-medium tabular-nums tracking-tight text-foreground">
                                {student.code}
                            </dd>
                        </div>
                        {student.cpf ? (
                            <div className="px-2.5 py-2 sm:px-3 sm:py-2.5">
                                <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    CPF
                                </dt>
                                <dd className="mt-0.5 font-mono text-xs font-medium tabular-nums tracking-tight text-foreground">
                                    {student.cpf}
                                </dd>
                            </div>
                        ) : null}
                        <div className="px-2.5 py-2 sm:px-3 sm:py-2.5">
                            <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                Curso
                            </dt>
                            <dd className="mt-0.5 text-xs font-medium leading-snug text-foreground line-clamp-3">
                                {student.course}
                            </dd>
                        </div>
                        <div className="grid grid-cols-2 gap-2 px-2.5 py-2 sm:gap-3 sm:px-3 sm:py-2.5">
                            <div className="min-w-0">
                                <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    Período
                                </dt>
                                <dd className="mt-0.5 text-xs font-medium text-foreground">
                                    {student.semester}
                                </dd>
                            </div>
                            <div className="min-w-0 border-l border-border/25 pl-2.5 dark:border-border/40 sm:pl-3">
                                <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    Unidade
                                </dt>
                                <dd className="mt-0.5 text-xs font-medium leading-snug text-foreground line-clamp-2">
                                    {student.unit}
                                </dd>
                            </div>
                        </div>
                    </div>
                </dl>
        </div>
    );
}

function FlowIntakeContextPanel({
    isSearching,
    hasStudent,
    student,
    requestedProcesses,
    canStart,
    hasBlockingPendency,
    financialPendency,
    dossierUrl,
    contextTab,
    onContextTabChange,
    selectedRequirement,
    onStartRequirement,
}: {
    isSearching: boolean;
    hasStudent: boolean;
    student: Student | null;
    requestedProcesses: RequestedProcess[];
    canStart: boolean;
    hasBlockingPendency: boolean;
    financialPendency: Pendency | undefined;
    dossierUrl: string | null;
    contextTab: IntakeContextTabId;
    onContextTabChange: (tab: IntakeContextTabId) => void;
    selectedRequirement: Requirement | null;
    onStartRequirement: (workflowId: number) => void;
}) {
    return (
        <aside
            className={cn(
                'flex h-full min-h-0 w-full min-w-0 shrink-0 flex-col border-t border-border bg-background',
                'lg:max-w-none lg:flex-row lg:border-t-0',
            )}
            aria-label="Painel lateral de contexto da abertura de processo"
        >
            <div
                role="tabpanel"
                id={`flow-intake-context-tabpanel-${contextTab}`}
                aria-labelledby={`flow-intake-context-tab-${contextTab}`}
                className="scrollbar-discrete flex min-h-0 min-w-0 flex-1 flex-col gap-3 overflow-y-auto p-3 sm:p-4 lg:min-w-0 lg:p-4 lg:pl-5 lg:pr-4"
            >
                {contextTab === 'details' ? (
                    <>
                        <header className="space-y-1">
                            <p className="font-mono text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                Abertura de processo
                            </p>
                            <h2
                                id="flow-intake-context-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                Contexto
                            </h2>
                            <p className="text-sm leading-snug text-muted-foreground">
                                {isSearching
                                    ? 'A carregar dados do estudante…'
                                    : !hasStudent
                                      ? 'Localize um estudante à esquerda para ver o resumo e os processos em curso.'
                                      : canStart && !hasBlockingPendency
                                        ? 'Pode iniciar requerimentos para este estudante.'
                                        : hasBlockingPendency
                                          ? 'Existem pendências a tratar antes de iniciar novos requerimentos.'
                                          : 'Selecione um requerimento no catálogo para continuar.'}
                            </p>
                        </header>

                        <Separator />

                        {student ? (
                            <div className="space-y-3">
                                <FlowIntakeStudentProfileCard
                                    student={student}
                                    financialPendency={financialPendency}
                                    dossierUrl={dossierUrl}
                                />

                                <div>
                                    <p className="pb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                        Processos do aluno (
                                        {requestedProcesses.length})
                                    </p>
                                    {requestedProcesses.length === 0 ? (
                                        <p className="rounded-lg bg-muted/40 px-2.5 py-2 text-xs leading-relaxed text-muted-foreground">
                                            Nenhum processo solicitado nesta
                                            demonstração.
                                        </p>
                                    ) : (
                                        <ul
                                            className="flex flex-col gap-1.5"
                                            role="list"
                                        >
                                            {requestedProcesses.map((p) => (
                                                <li
                                                    key={p.id}
                                                    className="rounded-lg border-0 bg-muted/35 px-2.5 py-2 ring-1 ring-border/40 dark:bg-muted/25"
                                                    role="listitem"
                                                >
                                                    <p className="text-xs font-medium leading-snug text-foreground line-clamp-2">
                                                        {p.title}
                                                    </p>
                                                    <p className="mt-0.5 text-[10px] text-muted-foreground">
                                                        {p.status} ·{' '}
                                                        {p.created_at}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </>
                ) : null}
                {contextTab === 'service' ? (
                    <div className="flex min-h-0 flex-1 flex-col gap-3">
                        {!selectedRequirement ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 px-1 py-10 text-center">
                                <Info
                                    className="size-9 text-muted-foreground/40"
                                    strokeWidth={1.5}
                                    aria-hidden
                                />
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Toque num requerimento na lista à esquerda
                                    para abrir o guia do serviço, público-alvo,
                                    orientação sobre valores e o botão para
                                    iniciar o processo a partir deste painel.
                                </p>
                            </div>
                        ) : (
                            <>
                                <header className="space-y-2">
                                    <p className="font-mono text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                        Serviço
                                    </p>
                                    <h2
                                        id="flow-intake-service-title"
                                        className="text-lg font-semibold leading-snug tracking-tight text-foreground"
                                    >
                                        {selectedRequirement.title}
                                    </h2>
                                    <div className="flex flex-wrap gap-1">
                                        <Badge variant="outline" className="text-[10px] font-medium">
                                            {selectedRequirement.group}
                                        </Badge>
                                        {selectedRequirement.tags
                                            .slice(0, 6)
                                            .map((t) => (
                                                <Badge
                                                    key={t}
                                                    variant="secondary"
                                                    className="text-[10px] font-normal"
                                                >
                                                    {t}
                                                </Badge>
                                            ))}
                                    </div>
                                </header>

                                <Separator />

                                <section className="space-y-1.5">
                                    <h3 className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                        O que é
                                    </h3>
                                    <p className="text-sm leading-relaxed text-foreground whitespace-pre-wrap">
                                        {selectedRequirement.description?.trim() ||
                                            `Fluxo associado a «${selectedRequirement.title}». Utilize a descrição oficial do catálogo quando existir; este texto resume o tipo de processo para orientação do atendimento.`}
                                    </p>
                                </section>

                                {(() => {
                                    const guide =
                                        requirementServiceGuidance(
                                            selectedRequirement,
                                        );

                                    return (
                                        <>
                                            <section className="space-y-1.5 rounded-lg bg-accent/30 px-3 py-2.5 dark:bg-accent/15">
                                                <h3 className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Para quem se destina
                                                </h3>
                                                <p className="text-xs leading-relaxed text-foreground">
                                                    {guide.audience}
                                                </p>
                                            </section>
                                            <section className="space-y-1.5 rounded-lg border border-border/50 bg-muted/35 px-3 py-2.5 dark:bg-muted/20">
                                                <h3 className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Taxas e valores
                                                </h3>
                                                <p className="text-xs leading-relaxed text-foreground">
                                                    {guide.feeLabel}
                                                </p>
                                            </section>
                                        </>
                                    );
                                })()}

                                <Separator />

                                <div className="space-y-2">
                                    <Button
                                        type="button"
                                        className="w-full"
                                        disabled={!canStart}
                                        onClick={() =>
                                            onStartRequirement(
                                                selectedRequirement.id,
                                            )
                                        }
                                    >
                                        Iniciar este requerimento
                                    </Button>
                                    {!canStart ? (
                                        <p className="text-center text-[11px] leading-snug text-muted-foreground">
                                            {hasBlockingPendency
                                                ? 'Regularize as pendências do aluno para permitir a abertura.'
                                                : hasStudent
                                                  ? 'Não é possível iniciar neste momento.'
                                                  : 'Localize um aluno antes de iniciar.'}
                                        </p>
                                    ) : null}
                                </div>
                            </>
                        )}
                    </div>
                ) : null}
                {contextTab === 'activities' ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 px-2 py-12 text-center text-muted-foreground">
                        <MessageSquare
                            className="size-10 text-muted-foreground/35"
                            strokeWidth={1.25}
                            aria-hidden
                        />
                        <p className="text-sm leading-relaxed">
                            Atividades da abertura de processo estarão disponíveis
                            em breve.
                        </p>
                    </div>
                ) : null}
                {contextTab === 'ai' ? (
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
                aria-label="Secções do painel de contexto"
                className="flex shrink-0 flex-row items-stretch justify-around gap-0.5 border-t border-border bg-background px-1.5 py-2 lg:w-[4.5rem] lg:flex-col lg:justify-start lg:gap-1 lg:border-t-0 lg:border-l lg:px-2 lg:py-3"
            >
                {INTAKE_CONTEXT_SIDE_TABS.map((tab) => {
                    const TabIcon = tab.icon;
                    const selected = contextTab === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            id={`flow-intake-context-tab-${tab.id}`}
                            aria-selected={selected}
                            aria-controls={`flow-intake-context-tabpanel-${tab.id}`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => {
                                onContextTabChange(tab.id);
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

export default function FlowsNew({
    query,
    requirements,
    popularRequirementIds,
    requestedProcesses,
}: PageProps) {
    const [q, setQ] = useState(query);
    const [isSearching, setIsSearching] = useState(false);
    const [searchResult, setSearchResult] = useState<SearchResponse | null>(
        null,
    );
    const [overrideStatus, setOverrideStatus] = useState<OverrideStatus>('none');
    const [negotiationOpen, setNegotiationOpen] = useState(false);
    const [selectedNegotiationId, setSelectedNegotiationId] =
        useState<string>('cash-10');
    const [isGeneratingArtifacts, setIsGeneratingArtifacts] = useState(false);
    const [artifacts, setArtifacts] = useState<NegotiationArtifacts | null>(
        null,
    );
    const [financialResolved, setFinancialResolved] = useState(false);

    const [activeTab, setActiveTab] = useState<'catalog' | 'requested'>(
        'catalog',
    );
    const [filterText, setFilterText] = useState('');
    const [catalogPill, setCatalogPill] =
        useState<CatalogCategoryPill>('all');
    const [selectedRequirementId, setSelectedRequirementId] = useState<
        number | null
    >(null);
    const [intakeContextTab, setIntakeContextTab] =
        useState<IntakeContextTabId>('details');

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
        id: 'flow-intake-resize',
        panelIds: ['flow-intake-primary', 'flow-intake-context'],
        storage: ssrSafeLocalStorage,
    });

    const canSearch = useMemo(() => q.trim().length >= 3, [q]);

    const submit = useCallback(() => {
        if (!canSearch) {
            return;
        }

        router.get(
            flowsIntake.url({ query: { q } }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }, [canSearch, q]);

    const runSearch = useCallback(async (queryToSearch: string) => {
        const trimmed = queryToSearch.trim();
        if (trimmed.length < 3) {
            setSearchResult(null);
            setOverrideStatus('none');
            setFinancialResolved(false);
            setArtifacts(null);
            return;
        }

        setIsSearching(true);
        setArtifacts(null);
        setFinancialResolved(false);
        setSearchResult(null);
        setOverrideStatus('none');

        try {
            const url = studentsSearch.url({ query: { q: trimmed } });
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Falha ao buscar estudante.');
            }

            const json = (await response.json()) as SearchResponse;
            setSearchResult(json);
            setOverrideStatus(json.override_status);
        } finally {
            setIsSearching(false);
        }
    }, []);

    useEffect(() => {
        void runSearch(query);
    }, [query, runSearch]);

    useEffect(() => {
        if (negotiationOpen) {
            setSelectedNegotiationId('cash-10');
        }
    }, [negotiationOpen]);

    const hasStudent = searchResult?.student !== null && searchResult !== null;
    const pendencies = searchResult?.pendencies ?? [];
    const financialPendency = pendencies.find((p) => p.type === 'financial');
    const hasBlockingNonFinancialPendency =
        pendencies.some((p) => p.type !== 'financial') &&
        overrideStatus !== 'approved';
    const hasBlockingFinancialPendency = Boolean(financialPendency) && !financialResolved;
    const hasBlockingPendency =
        hasBlockingNonFinancialPendency || hasBlockingFinancialPendency;

    const pendencyBannerTitle = useMemo(() => {
        if (
            financialPendency &&
            typeof financialPendency.amount === 'number'
        ) {
            return `Pendência financeira: ${formatBrl(financialPendency.amount)}`;
        }
        if (financialPendency) {
            return 'Pendência financeira';
        }
        if (
            pendencies.length === 1 &&
            pendencies[0]?.type === 'academic'
        ) {
            return 'Pendência acadêmica';
        }

        return 'Pendências do aluno';
    }, [financialPendency, pendencies]);

    const pendencyBannerSubtitle = useMemo(() => {
        if (hasBlockingFinancialPendency && financialPendency) {
            return 'Regularize para habilitar novos requerimentos.';
        }
        if (hasBlockingNonFinancialPendency) {
            return 'Regularize a documentação ou solicite autorização para continuar.';
        }

        return 'Consulte os detalhes abaixo quando necessário.';
    }, [
        financialPendency,
        hasBlockingFinancialPendency,
        hasBlockingNonFinancialPendency,
    ]);

    const requestOverride = useCallback(async () => {
        const studentId = searchResult?.student?.id;
        if (!studentId) {
            return;
        }

        setOverrideStatus('requested');
        const response = await fetch(overridesStore.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ student_id: studentId }),
        });

        if (!response.ok) {
            setOverrideStatus('none');
            return;
        }

        const json = (await response.json()) as { override_status: OverrideStatus };
        setOverrideStatus(json.override_status);
    }, [searchResult?.student?.id]);

    const simulateApproveOverride = useCallback(async () => {
        const studentId = searchResult?.student?.id;
        if (!studentId) {
            return;
        }

        const response = await fetch(overridesStore.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ student_id: studentId, simulate_approve: true }),
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as { override_status: OverrideStatus };
        setOverrideStatus(json.override_status);
    }, [searchResult?.student?.id]);

    const generateArtifacts = useCallback(
        async (optionId: string) => {
            const studentId = searchResult?.student?.id;
            if (!studentId) {
                return;
            }

            setIsGeneratingArtifacts(true);
            setArtifacts(null);
            try {
                const response = await fetch(negotiationsStore.url(), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        option_id: optionId,
                    }),
                });

                if (!response.ok) {
                    return;
                }

                const json = (await response.json()) as NegotiationArtifacts;
                setArtifacts(json);
                setFinancialResolved(true);
            } finally {
                setIsGeneratingArtifacts(false);
            }
        },
        [searchResult?.student?.id],
    );

    const dossierUrl = useMemo(() => {
        const studentId = searchResult?.student?.id;
        if (!studentId) {
            return null;
        }

        return `/students/${studentId}`;
    }, [searchResult?.student?.id]);

    const negotiationDebtTotal = useMemo(() => {
        if (
            financialPendency &&
            typeof financialPendency.amount === 'number'
        ) {
            return financialPendency.amount;
        }

        return 1250;
    }, [financialPendency]);

    const negotiationPlans = useMemo(() => {
        const total = negotiationDebtTotal;
        const cashTotal = Math.round(total * 0.9 * 100) / 100;
        const savings = Math.round((total - cashTotal) * 100) / 100;
        const per3 = Math.round((total / 3) * 100) / 100;
        const per6 = Math.round((total / 6) * 100) / 100;

        return [
            {
                id: 'cash-10',
                title: 'À vista (10% desc.)',
                amountLine: formatBrl(cashTotal),
                footer: `Economia de ${formatBrl(savings)} no pagamento único.`,
                highlight: null as string | null,
                recommended: true,
            },
            {
                id: '3x',
                title: 'Parcelado em 3x',
                amountLine: `3x ${formatBrl(per3)}`,
                footer: `Valor total de ${formatBrl(total)}.`,
                highlight: 'Sem juros',
                recommended: false,
            },
            {
                id: '6x',
                title: 'Parcelado em 6x',
                amountLine: `6x ${formatBrl(per6)}`,
                footer: 'Inclui taxas administrativas de parcelamento.',
                highlight: null as string | null,
                recommended: false,
            },
        ];
    }, [negotiationDebtTotal]);

    const visibleRequirements = useMemo(() => {
        const t = filterText.trim().toLowerCase();
        if (!t) {
            return requirements;
        }

        return requirements.filter((r) => {
            const hay = `${r.title} ${r.description ?? ''} ${r.tags.join(' ')} ${r.group}`.toLowerCase();
            return hay.includes(t);
        });
    }, [filterText, requirements]);

    const pillFilteredRequirements = useMemo(
        () =>
            visibleRequirements.filter((r) =>
                requirementMatchesCatalogPill(
                    r,
                    catalogPill,
                    popularRequirementIds,
                ),
            ),
        [catalogPill, popularRequirementIds, visibleRequirements],
    );

    const popularRequirements = useMemo(() => {
        const popularSet = new Set(popularRequirementIds);
        return pillFilteredRequirements
            .filter((r) => popularSet.has(r.id))
            .slice(0, 6);
    }, [pillFilteredRequirements, popularRequirementIds]);

    const recentRequirements = useMemo(() => {
        const ids = readRecentRequirementIds();
        if (ids.length === 0) {
            return [];
        }

        const popularIds = new Set(popularRequirements.map((r) => r.id));
        const index = new Map(
            pillFilteredRequirements.map((r) => [r.id, r]),
        );

        const out: Requirement[] = [];
        for (const id of ids) {
            if (popularIds.has(id)) {
                continue;
            }

            const req = index.get(id);
            if (req) {
                out.push(req);
            }
        }

        return out;
    }, [pillFilteredRequirements, popularRequirements]);

    const catalogRequirements = useMemo(() => {
        const used = new Set<number>([
            ...popularRequirements.map((r) => r.id),
            ...recentRequirements.map((r) => r.id),
        ]);

        return pillFilteredRequirements.filter((r) => !used.has(r.id));
    }, [pillFilteredRequirements, popularRequirements, recentRequirements]);

    const selectedCatalogRequirement = useMemo(
        () =>
            requirements.find((r) => r.id === selectedRequirementId) ?? null,
        [requirements, selectedRequirementId],
    );

    const selectRequirement = useCallback((id: number) => {
        setSelectedRequirementId(id);
        setIntakeContextTab('service');
        const current = readRecentRequirementIds().filter((x) => x !== id);
        writeRecentRequirementIds([id, ...current].slice(0, 8));
    }, []);

    const canStart = hasStudent && !hasBlockingPendency;

    const startRequirement = useCallback(
        (workflowId: number) => {
            const student = searchResult?.student;
            if (!student || !canStart) {
                return;
            }

            router.post(
                flowsRunsStore.url({ workflow: workflowId }),
                {
                    student_id: student.id,
                    student_code: student.code,
                    student_name: student.name,
                },
                {
                    preserveScroll: true,
                },
            );
        },
        [canStart, searchResult?.student],
    );

    const renderPrimary = () => {
        const student = searchResult?.student ?? null;

        return (
            <>
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Abertura de requerimento
                </h1>
                <p className="text-sm text-muted-foreground">
                    {hasStudent
                        ? 'Selecione o requerimento e inicie o processo para este aluno.'
                        : 'Comece por localizar o aluno. Os requerimentos ficam disponíveis na etapa seguinte.'}
                </p>
            </div>


            <CardContent className="space-y-2 px-6 pb-0 pt-2">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-stretch">
                            <div className="relative min-w-0 flex-1">
                                <UserSearch
                                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden
                                />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Código, nome, email ou CPF…"
                                    className={cn(
                                        'h-10 pl-9 shadow-xs',
                                        !hasStudent &&
                                            'border-border bg-background text-foreground',
                                    )}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            submit();
                                        }
                                    }}
                                />
                            </div>
                            <Button
                                type="button"
                                className="h-10 shrink-0 px-5"
                                disabled={!canSearch}
                                onClick={submit}
                            >
                                Consultar
                            </Button>
                        </div>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Digite pelo menos 3 caracteres para consultar. Exemplos
                            de demonstração:{' '}
                            <span className="font-medium text-foreground/80">
                                maria
                            </span>{' '}
                            ou{' '}
                            <span className="font-medium text-foreground/80">
                                20240001
                            </span>{' '}
                            (sem pendências); inclua{' '}
                            <span className="font-medium text-foreground/80">
                                fin
                            </span>
                            ,{' '}
                            <span className="font-medium text-foreground/80">
                                deb
                            </span>{' '}
                            ou{' '}
                            <span className="font-medium text-foreground/80">
                                div
                            </span>{' '}
                            para simular dívida;{' '}
                            <span className="font-medium text-foreground/80">
                                acad
                            </span>{' '}
                            ou{' '}
                            <span className="font-medium text-foreground/80">
                                doc
                            </span>{' '}
                            para pendência académica.
                        </p>
                </CardContent>

        

                {isSearching ||
                (hasStudent && pendencies.length > 0) ? (
                <div className="min-w-0 space-y-3 px-6">
                {isSearching ? (
                    <Alert>
                        <LoaderCircle className="animate-spin" aria-hidden />
                        <AlertTitle>A consultar…</AlertTitle>
                        <AlertDescription>
                            Buscando estudante e pendências.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {hasStudent && pendencies.length > 0 ? (
                    <Collapsible defaultOpen={false}>
                        <div
                            className={cn(
                                'w-full overflow-hidden rounded-xl border border-border/60 bg-muted/30 text-foreground shadow-none ring-1 ring-border/35 dark:bg-muted/20',
                                hasBlockingPendency &&
                                    'border-l-2 border-l-amber-500/90 dark:border-l-amber-500/80',
                            )}
                        >
                            <CollapsibleTrigger asChild>
                                <button
                                    type="button"
                                    aria-label="Mostrar ou ocultar detalhes das pendências"
                                    className={cn(
                                        'group flex w-full cursor-pointer items-start gap-3 px-6 py-4 text-left outline-none transition-colors',
                                        'hover:bg-muted/45 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                    )}
                                >
                                    <div
                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-background/80 text-amber-600 ring-1 ring-border/50 dark:text-amber-500"
                                        aria-hidden
                                    >
                                        <AlertTriangle
                                            className="size-4"
                                            strokeWidth={2}
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-1 pr-1">
                                        <h3 className="text-sm font-semibold leading-snug tracking-tight text-foreground">
                                            {pendencyBannerTitle}
                                        </h3>
                                        <p className="text-sm leading-relaxed text-muted-foreground">
                                            {pendencyBannerSubtitle}
                                        </p>
                                    </div>
                                    <ChevronDown
                                        className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180"
                                        aria-hidden
                                    />
                                </button>
                            </CollapsibleTrigger>

                            <CollapsibleContent className="overflow-hidden">
                                <div className="space-y-3 border-t border-border/50 px-6 pb-4 pt-4">
                                    <ul className="space-y-2 text-sm leading-relaxed text-muted-foreground">
                                        {pendencies.map((p) => (
                                            <li
                                                key={p.id}
                                                className="flex gap-2.5"
                                            >
                                                <span
                                                    className="mt-2 size-1 shrink-0 rounded-full bg-muted-foreground/35"
                                                    aria-hidden
                                                />
                                                <span className="text-foreground/90">
                                                    {p.summary}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="flex flex-wrap items-center gap-2">
                                        {financialPendency ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    setNegotiationOpen(true)
                                                }
                                            >
                                                Negociar
                                            </Button>
                                        ) : null}

                                        {hasBlockingFinancialPendency ? (
                                            artifacts ? (
                                                <Badge variant="secondary">
                                                    Acordo gerado
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="secondary"
                                                    className="max-w-full whitespace-normal text-left leading-snug"
                                                >
                                                    Negociação necessária para
                                                    continuar
                                                </Badge>
                                            )
                                        ) : null}

                                        {hasBlockingNonFinancialPendency ? (
                                            overrideStatus === 'requested' ? (
                                                <>
                                                    <Badge
                                                        variant="secondary"
                                                        className="max-w-full whitespace-normal text-left leading-snug"
                                                    >
                                                        Quebra solicitada
                                                        (aguardando gestor)
                                                    </Badge>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={
                                                            simulateApproveOverride
                                                        }
                                                    >
                                                        Simular aprovação
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={requestOverride}
                                                >
                                                    Solicitar quebra
                                                </Button>
                                            )
                                        ) : null}
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </div>
                    </Collapsible>
                ) : null}
                </div>
                ) : null}

                {student ? (
                <Card className="box-content overflow-hidden border-0 shadow-none">
                    <CardContent className="p-0">
                        <div className="px-6 pt-6">
                            <div className="flex flex-col gap-3 border-b border-border pb-3 sm:flex-row sm:items-end sm:justify-between sm:gap-6">
                                <div
                                    className="flex min-w-0 flex-wrap gap-6 sm:gap-8"
                                    role="tablist"
                                    aria-label="Requerimentos — tipo de listagem"
                                >
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={activeTab === 'catalog'}
                                        onClick={() => setActiveTab('catalog')}
                                        className={cn(
                                            'flex items-center gap-2 border-b-2 pb-3 text-sm transition-colors',
                                            activeTab === 'catalog'
                                                ? 'border-foreground font-semibold text-foreground'
                                                : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        Novo requerimento
                                        <span
                                            className={cn(
                                                'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-semibold tabular-nums',
                                                activeTab === 'catalog'
                                                    ? 'bg-foreground text-background'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {requirements.length}
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={
                                            activeTab === 'requested'
                                        }
                                        onClick={() =>
                                            setActiveTab('requested')
                                        }
                                        className={cn(
                                            'flex items-center gap-2 border-b-2 pb-3 text-sm transition-colors',
                                            activeTab === 'requested'
                                                ? 'border-foreground font-semibold text-foreground'
                                                : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        Requerimentos do aluno
                                        <span
                                            className={cn(
                                                'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-semibold tabular-nums',
                                                activeTab === 'requested'
                                                    ? 'bg-foreground text-background'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {requestedProcesses.length}
                                        </span>
                                    </button>
                                </div>
                                {activeTab === 'catalog' ? (
                                    <div className="relative w-full min-w-0 shrink-0 sm:max-w-xs">
                                        <label
                                            htmlFor="flows-intake-service-filter"
                                            className="sr-only"
                                        >
                                            Filtrar serviços
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <Input
                                            id="flows-intake-service-filter"
                                            value={filterText}
                                            onChange={(e) =>
                                                setFilterText(e.target.value)
                                            }
                                            placeholder="Filtrar serviços…"
                                            className={cn(
                                                'my-0 h-10 border-border/80 bg-background py-0 pl-9 pr-3 text-sm leading-10 shadow-xs',
                                                'placeholder:text-muted-foreground',
                                            )}
                                        />
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        {activeTab === 'catalog' ? (
                            <div className="border-b border-border/80 bg-muted/25 px-6 py-3 sm:py-3.5">
                                <div
                                    className="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Filtrar por categoria"
                                >
                                    {CATALOG_PILL_OPTIONS.map((opt) => {
                                        const active = catalogPill === opt.id;

                                        return (
                                            <button
                                                key={opt.id}
                                                type="button"
                                                onClick={() =>
                                                    setCatalogPill(opt.id)
                                                }
                                                className={cn(
                                                    'rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                                                    active
                                                        ? 'bg-chart-2 text-white shadow-sm dark:bg-chart-2 dark:text-white'
                                                        : 'bg-background/80 text-muted-foreground hover:bg-background hover:text-foreground',
                                                )}
                                            >
                                                {opt.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : null}

                        <div className="space-y-6 px-6 py-6">
                            {activeTab === 'requested' ? (
                                requestedProcesses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum processo solicitado (mock).
                                    </p>
                                ) : (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {requestedProcesses.map((p) => (
                                            <Card key={p.id}>
                                                <CardHeader className="pb-2">
                                                    <CardTitle className="text-base">
                                                        {p.title}
                                                    </CardTitle>
                                                    <p className="text-sm text-muted-foreground">
                                                        {p.status} · {p.created_at}
                                                    </p>
                                                </CardHeader>
                                            </Card>
                                        ))}
                                    </div>
                                )
                            ) : (
                                <>
                                    {popularRequirements.length > 0 ? (
                                        <section className="space-y-2">
                                            <div className="flex items-center justify-between gap-4">
                                                <h3 className="text-sm font-medium">
                                                    Mais usados
                                                </h3>
                                                <span className="text-xs text-muted-foreground">
                                                    Atalho
                                                </span>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                {popularRequirements.map((r) => (
                                                    <Card
                                                        key={r.id}
                                                        role="button"
                                                        tabIndex={0}
                                                        className={cn(
                                                            'h-full gap-0 cursor-pointer transition-colors hover:bg-accent/20',
                                                            selectedRequirementId ===
                                                                r.id && 'ring-2 ring-ring',
                                                        )}
                                                        onClick={() =>
                                                            selectRequirement(r.id)
                                                        }
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                selectRequirement(r.id);
                                                            }
                                                        }}
                                                    >
                                                        <CardHeader className="pb-2">
                                                            <CardTitle className="text-base">
                                                                {r.title}
                                                            </CardTitle>
                                                            {r.description ? (
                                                                <p className="mt-1 text-sm text-muted-foreground line-clamp-2">
                                                                    {r.description}
                                                                </p>
                                                            ) : null}
                                                            
                                                        </CardHeader>
                                                        <CardContent className="mt-auto pt-2">
                                                            <Button
                                                                type="button"
                                                                disabled={!canStart}
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    startRequirement(r.id);
                                                                }}
                                                            >
                                                                Iniciar
                                                            </Button>
                                                        </CardContent>
                                                    </Card>
                                                ))}
                                            </div>
                                        </section>
                                    ) : null}

                                    {recentRequirements.length > 0 ? (
                                        <section className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Últimos selecionados
                                            </h3>
                                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                {recentRequirements.map((r) => (
                                                    <Card
                                                        key={r.id}
                                                        role="button"
                                                        tabIndex={0}
                                                        className={cn(
                                                            'h-full gap-0 cursor-pointer transition-colors hover:bg-accent/20',
                                                            selectedRequirementId ===
                                                                r.id && 'ring-2 ring-ring',
                                                        )}
                                                        onClick={() =>
                                                            selectRequirement(r.id)
                                                        }
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                selectRequirement(r.id);
                                                            }
                                                        }}
                                                    >
                                                        <CardHeader className="pb-2">
                                                            <CardTitle className="text-base">
                                                                {r.title}
                                                            </CardTitle>
                                                            <div className="mt-2 flex flex-wrap gap-1">
                                                                {r.tags
                                                                    .slice(0, 3)
                                                                    .map((t) => (
                                                                        <Badge
                                                                            key={t}
                                                                            variant="secondary"
                                                                        >
                                                                            {t}
                                                                        </Badge>
                                                                    ))}
                                                            </div>
                                                        </CardHeader>
                                                        <CardContent className="mt-auto pt-2">
                                                            <Button
                                                                type="button"
                                                                disabled={!canStart}
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    startRequirement(r.id);
                                                                }}
                                                            >
                                                                Iniciar
                                                            </Button>
                                                        </CardContent>
                                                    </Card>
                                                ))}
                                            </div>
                                        </section>
                                    ) : null}

                                    {catalogRequirements.length > 0 ? (
                                        <section className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Catálogo
                                            </h3>
                                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                {catalogRequirements.map(
                                                    (r) => (
                                                        <Card
                                                            key={r.id}
                                                            role="button"
                                                            tabIndex={0}
                                                            className={cn(
                                                                'h-full gap-0 cursor-pointer transition-colors hover:bg-accent/20',
                                                                selectedRequirementId ===
                                                                    r.id &&
                                                                    'ring-2 ring-ring',
                                                            )}
                                                            onClick={() =>
                                                                selectRequirement(
                                                                    r.id,
                                                                )
                                                            }
                                                            onKeyDown={(e) => {
                                                                if (
                                                                    e.key ===
                                                                    'Enter'
                                                                ) {
                                                                    selectRequirement(
                                                                        r.id,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <CardHeader className="pb-2">
                                                                <CardTitle className="text-base">
                                                                    {r.title}
                                                                </CardTitle>
                                                                {r.description ? (
                                                                    <p className="mt-1 text-sm text-muted-foreground line-clamp-2">
                                                                        {
                                                                            r.description
                                                                        }
                                                                    </p>
                                                                ) : null}
                                                                <div className="mt-2 flex flex-wrap gap-1">
                                                                    {r.tags
                                                                        .slice(
                                                                            0,
                                                                            3,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                t,
                                                                            ) => (
                                                                                <Badge
                                                                                    key={
                                                                                        t
                                                                                    }
                                                                                    variant="secondary"
                                                                                >
                                                                                    {
                                                                                        t
                                                                                    }
                                                                                </Badge>
                                                                            ),
                                                                        )}
                                                                </div>
                                                            </CardHeader>
                                                            <CardContent className="mt-auto flex items-center justify-between gap-2 pt-2">
                                                                <Badge variant="outline">
                                                                    {r.group}
                                                                </Badge>
                                                                <Button
                                                                    type="button"
                                                                    disabled={
                                                                        !canStart
                                                                    }
                                                                    onClick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        startRequirement(
                                                                            r.id,
                                                                        );
                                                                    }}
                                                                >
                                                                    Iniciar
                                                                </Button>
                                                            </CardContent>
                                                        </Card>
                                                    ),
                                                )}
                                            </div>
                                        </section>
                                    ) : null}

                                    {pillFilteredRequirements.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {requirements.length === 0
                                                ? 'Nenhum requerimento disponível.'
                                                : filterText.trim() ||
                                                    catalogPill !== 'all'
                                                  ? 'Nenhum requerimento corresponde à categoria ou ao texto de filtro.'
                                                  : null}
                                        </p>
                                    ) : null}
                                </>
                            )}
                        </div>
                    </CardContent>
                </Card>
                ) : null}
        </>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Novo processo" />
            <div className="flex min-h-0 flex-1 flex-col lg:max-h-[calc(100svh-4rem-var(--impersonation-banner-offset,0px))] lg:min-h-0">
                {lgUp ? (
                    <ResizablePanelGroup
                        orientation="horizontal"
                        className="flex min-h-0 flex-1"
                        defaultLayout={layoutPersistence.defaultLayout}
                        onLayoutChanged={layoutPersistence.onLayoutChanged}
                    >
                        <ResizablePanel
                            id="flow-intake-primary"
                            className="flex min-h-0 min-w-0"
                            minSize="24%"
                        >
                            <IntakeScrollWrap>
                                {renderPrimary()}
                            </IntakeScrollWrap>
                        </ResizablePanel>
                        <ResizableHandle
                            withHandle
                            className="bg-border/60"
                            aria-label="Redimensionar painel de contexto"
                        />
                        <ResizablePanel
                            id="flow-intake-context"
                            className="flex min-h-0 min-w-0"
                            minSize={`${MIN_FLOW_INTAKE_CONTEXT_PX}px`}
                            maxSize={`${MAX_FLOW_INTAKE_CONTEXT_PX}px`}
                        >
                            <FlowIntakeContextPanel
                                isSearching={isSearching}
                                hasStudent={hasStudent}
                                student={searchResult?.student ?? null}
                                requestedProcesses={requestedProcesses}
                                canStart={canStart}
                                hasBlockingPendency={hasBlockingPendency}
                                financialPendency={financialPendency}
                                dossierUrl={dossierUrl}
                                contextTab={intakeContextTab}
                                onContextTabChange={setIntakeContextTab}
                                selectedRequirement={selectedCatalogRequirement}
                                onStartRequirement={startRequirement}
                            />
                        </ResizablePanel>
                    </ResizablePanelGroup>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col">
                        <IntakeScrollWrap>
                            {renderPrimary()}
                        </IntakeScrollWrap>
                        <FlowIntakeContextPanel
                            isSearching={isSearching}
                            hasStudent={hasStudent}
                            student={searchResult?.student ?? null}
                            requestedProcesses={requestedProcesses}
                            canStart={canStart}
                            hasBlockingPendency={hasBlockingPendency}
                            financialPendency={financialPendency}
                            dossierUrl={dossierUrl}
                            contextTab={intakeContextTab}
                            onContextTabChange={setIntakeContextTab}
                            selectedRequirement={selectedCatalogRequirement}
                            onStartRequirement={startRequirement}
                        />
                    </div>
                )}
            </div>

            <Sheet open={negotiationOpen} onOpenChange={setNegotiationOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 p-0 sm:max-w-md"
                >
                    <SheetHeader className="gap-0 space-y-0 border-b border-border/60 bg-muted/15 p-0 px-6 pb-6 pt-6 pr-14 text-left dark:bg-muted/10">
                        <SheetTitle className="text-base font-semibold tracking-tight text-foreground">
                            Simulação de negociação
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Escolha uma condição de pagamento para gerar boleto,
                            PIX e contrato de demonstração.
                        </SheetDescription>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {searchResult?.student?.name ?? 'Aluno'}
                        </p>
                        <div className="mt-5 rounded-lg bg-muted/30 p-4 dark:bg-muted/20">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                Valor em aberto
                            </p>
                            <p className="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight text-foreground">
                                {formatBrl(negotiationDebtTotal)}
                            </p>
                            {dossierUrl ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 h-8 w-full gap-1.5 text-xs font-medium shadow-none"
                                    asChild
                                >
                                    <a
                                        href={dossierUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Ver detalhes da dívida
                                        <ExternalLink
                                            className="size-3.5 shrink-0 opacity-70"
                                            aria-hidden
                                        />
                                    </a>
                                </Button>
                            ) : (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Consulte o financeiro para o detalhamento
                                    completo.
                                </p>
                            )}
                        </div>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        <div className="space-y-3">
                            {negotiationPlans.map((plan) => {
                                const selected =
                                    selectedNegotiationId === plan.id;

                                return (
                                    <button
                                        key={plan.id}
                                        type="button"
                                        onClick={() =>
                                            setSelectedNegotiationId(plan.id)
                                        }
                                        className={cn(
                                            'relative w-full rounded-lg border bg-card p-4 text-left shadow-sm transition-colors',
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                            plan.recommended
                                                ? selected
                                                    ? 'border-foreground ring-1 ring-foreground/15'
                                                    : 'border-foreground'
                                                : selected
                                                  ? 'border-primary/50 ring-1 ring-primary/15'
                                                  : 'border-border/70',
                                        )}
                                    >
                                        {plan.recommended ? (
                                            <span className="absolute left-4 top-3 rounded bg-teal-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-400">
                                                Recomendado
                                            </span>
                                        ) : null}
                                        <div
                                            className={cn(
                                                'grid gap-1 pr-10',
                                                plan.recommended ? 'pt-7' : '',
                                            )}
                                        >
                                            <div className="text-sm font-medium text-foreground">
                                                {plan.title}
                                            </div>
                                            <div className="flex flex-wrap items-baseline gap-2">
                                                <span className="text-lg font-semibold tabular-nums tracking-tight">
                                                    {plan.amountLine}
                                                </span>
                                                {plan.highlight ? (
                                                    <span className="text-xs font-medium text-teal-600 dark:text-teal-400">
                                                        {plan.highlight}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <p className="text-xs leading-snug text-muted-foreground">
                                                {plan.footer}
                                            </p>
                                        </div>
                                        <span
                                            className={cn(
                                                'absolute right-4 top-4 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                                selected
                                                    ? 'border-foreground bg-foreground'
                                                    : 'border-muted-foreground/35',
                                            )}
                                            aria-hidden
                                        >
                                            {selected ? (
                                                <span className="block h-2 w-2 rounded-full bg-background" />
                                            ) : null}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="mt-5 flex gap-3 rounded-md border border-sky-200/90 bg-sky-50/90 px-3.5 py-3 text-sm text-sky-950/80 dark:border-sky-900/50 dark:bg-sky-950/35 dark:text-sky-100/85">
                            <Info
                                className="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"
                                aria-hidden
                            />
                            <p className="leading-snug">
                                Ao confirmar, o boleto será gerado
                                automaticamente no seu painel financeiro e o
                                requerimento será desbloqueado para
                                preenchimento.
                            </p>
                        </div>

                        {artifacts ? (
                            <Card className="mt-5 border-dashed">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        Artefatos gerados
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <a
                                        className="text-primary underline underline-offset-4"
                                        href={artifacts.boleto_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Boleto
                                    </a>
                                    <a
                                        className="text-primary underline underline-offset-4"
                                        href={artifacts.pix_qr_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        PIX (QR)
                                    </a>
                                    <a
                                        className="text-primary underline underline-offset-4"
                                        href={artifacts.contrato_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Contrato
                                    </a>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>

                    <SheetFooter className="flex-col gap-3 border-t bg-background px-6 py-5 sm:flex-col">
                        <Button
                            type="button"
                            className="w-full"
                            disabled={isGeneratingArtifacts}
                            onClick={() =>
                                void generateArtifacts(selectedNegotiationId)
                            }
                        >
                            {isGeneratingArtifacts
                                ? 'A processar…'
                                : 'Fechar acordo e liberar requerimento'}
                        </Button>
                        <button
                            type="button"
                            className="text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            onClick={() => setNegotiationOpen(false)}
                        >
                            Cancelar simulação
                        </button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

