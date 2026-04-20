import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    ChevronDown,
    ExternalLink,
    Info,
    LoaderCircle,
    Search,
    UserSearch,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
            return `Pendência financeira detetada: ${formatBrl(financialPendency.amount)}`;
        }
        if (financialPendency) {
            return 'Pendência financeira detetada';
        }
        if (
            pendencies.length === 1 &&
            pendencies[0]?.type === 'academic'
        ) {
            return 'Pendência académica detetada';
        }

        return 'Pendências detetadas';
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

    const selectRequirement = useCallback((id: number) => {
        setSelectedRequirementId(id);
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Novo processo" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Abertura de processo
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {hasStudent
                            ? 'Selecione o requerimento e inicie o processo para este aluno.'
                            : 'Comece por localizar o aluno. Os requerimentos ficam disponíveis na etapa seguinte.'}
                    </p>
                </div>

                <Card
                    className={cn(
                        'gap-4',
                        !hasStudent &&
                            'box-content max-w-full border-0 bg-accent text-accent-foreground shadow-sm [&_.text-muted-foreground]:text-accent-foreground/75 [&_[data-slot=card-title]]:font-normal [&_[data-slot=card-title]]:text-accent-foreground/55',
                    )}
                >
                    <CardHeader className="space-y-0 px-6 pb-0 pt-6">
                        <CardTitle className="text-[0.65rem] font-normal uppercase tracking-[0.2em] text-muted-foreground/65">
                            Pesquisa de estudante
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 px-6 pb-6 pt-2">
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
                </Card>

                {isSearching ? (
                    <Alert>
                        <LoaderCircle className="animate-spin" aria-hidden />
                        <AlertTitle>A consultar…</AlertTitle>
                        <AlertDescription>
                            Buscando estudante e pendências.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {hasStudent ? (
                    <Card className="overflow-hidden shadow-sm">
                        <CardContent className="p-4 sm:p-6">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-stretch">
                                <div className="shrink-0 self-start">
                                    <Avatar className="size-24 rounded-xl">
                                        <AvatarImage
                                            src={
                                                searchResult.student
                                                    .avatar_url ?? undefined
                                            }
                                            alt=""
                                        />
                                        <AvatarFallback className="rounded-xl text-lg font-semibold">
                                            {studentInitials(
                                                searchResult.student.name,
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>

                                <div className="min-w-0 flex-1 space-y-4">
                                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="min-w-0 space-y-3">
                                            <h2 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                                {searchResult.student.name}
                                            </h2>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {financialPendency &&
                                                typeof financialPendency.amount ===
                                                    'number' ? (
                                                    <Badge
                                                        variant="destructive"
                                                        className="max-w-full font-medium"
                                                    >
                                                        <Banknote
                                                            className="shrink-0"
                                                            aria-hidden
                                                        />
                                                        <span className="truncate">
                                                            Dívida:{' '}
                                                            {formatBrl(
                                                                financialPendency.amount,
                                                            )}
                                                        </span>
                                                    </Badge>
                                                ) : financialPendency ? (
                                                    <Badge variant="destructive">
                                                        <Banknote
                                                            className="shrink-0"
                                                            aria-hidden
                                                        />
                                                        Pendência financeira
                                                    </Badge>
                                                ) : null}
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'border font-medium',
                                                        /ativo/i.test(
                                                            searchResult.student
                                                                .status,
                                                        )
                                                            ? 'border-chart-2/35 bg-chart-2/10 text-chart-2'
                                                            : 'border-border bg-muted/40 text-foreground',
                                                    )}
                                                >
                                                    {searchResult.student.status}
                                                </Badge>
                                            </div>
                                        </div>
                                        {dossierUrl ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="shrink-0 self-start"
                                                asChild
                                            >
                                                <a
                                                    href={dossierUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Abrir ficha completa
                                                    <ExternalLink
                                                        className="ml-2 size-4"
                                                        aria-hidden
                                                    />
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>

                                    <div className="rounded-lg border border-border/70 bg-muted/25 px-4 py-3 sm:px-5">
                                        <div
                                            className={cn(
                                                'grid gap-4',
                                                searchResult.student.cpf
                                                    ? 'sm:grid-cols-2 sm:gap-x-10'
                                                    : 'max-w-md',
                                            )}
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                                                    Matrícula
                                                </p>
                                                <p className="font-mono text-sm font-medium tabular-nums tracking-tight text-foreground">
                                                    {
                                                        searchResult.student
                                                            .code
                                                    }
                                                </p>
                                            </div>
                                            {searchResult.student.cpf ? (
                                                <div className="min-w-0 space-y-1 sm:border-l sm:border-border/60 sm:pl-10">
                                                    <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                                                        CPF
                                                    </p>
                                                    <p className="font-mono text-sm font-medium tabular-nums tracking-tight text-foreground">
                                                        {
                                                            searchResult.student
                                                                .cpf
                                                        }
                                                    </p>
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="mt-4 grid gap-4 border-t border-border/50 pt-4 sm:grid-cols-3 sm:gap-x-10">
                                            <div className="min-w-0 space-y-1">
                                                <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                                                    Curso matriculado
                                                </p>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {
                                                        searchResult.student
                                                            .course
                                                    }
                                                </p>
                                            </div>
                                            <div className="min-w-0 space-y-1 sm:border-l sm:border-border/60 sm:pl-10">
                                                <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                                                    Período atual
                                                </p>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {
                                                        searchResult.student
                                                            .semester
                                                    }
                                                </p>
                                            </div>
                                            <div className="min-w-0 space-y-1 sm:border-l sm:border-border/60 sm:pl-10">
                                                <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">
                                                    Unidade
                                                </p>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {
                                                        searchResult.student
                                                            .unit
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                {hasStudent && pendencies.length > 0 ? (
                    <Collapsible defaultOpen={false}>
                        <div
                            className={cn(
                                'rounded-xl border px-4 py-4 shadow-sm',
                                'border-amber-200/90 bg-amber-50/95 text-amber-950',
                                'dark:border-amber-800/55 dark:bg-amber-950/35 dark:text-amber-50',
                                hasBlockingPendency &&
                                    'ring-1 ring-amber-300/40 dark:ring-amber-700/30',
                            )}
                        >
                            <div className="flex items-start gap-3">
                                <div
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                                        'bg-amber-100/90 dark:bg-amber-900/50',
                                    )}
                                    aria-hidden
                                >
                                    <AlertTriangle
                                        className="size-5 text-amber-800 dark:text-amber-200"
                                        strokeWidth={2}
                                    />
                                </div>
                                <div className="min-w-0 flex-1 space-y-1 pr-1">
                                    <h3 className="text-base font-semibold leading-snug tracking-tight text-amber-950 dark:text-amber-50">
                                        {pendencyBannerTitle}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-amber-900/75 dark:text-amber-100/80">
                                        {pendencyBannerSubtitle}
                                    </p>
                                </div>
                                <CollapsibleTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className={cn(
                                            'size-9 shrink-0 text-amber-900/70 hover:bg-amber-100/80 hover:text-amber-950',
                                            'dark:text-amber-200/80 dark:hover:bg-amber-900/50 dark:hover:text-amber-50',
                                            '[&_svg]:transition-transform [&[data-state=open]_svg]:rotate-180',
                                        )}
                                        aria-label="Mostrar ou ocultar detalhes das pendências"
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                </CollapsibleTrigger>
                            </div>

                            <CollapsibleContent className="overflow-hidden">
                                <div
                                    className={cn(
                                        'mt-4 space-y-3 border-t pt-4',
                                        'border-amber-200/80 dark:border-amber-800/50',
                                    )}
                                >
                                    <ul className="space-y-2 text-sm text-amber-950/90 dark:text-amber-50/90">
                                        {pendencies.map((p) => (
                                            <li
                                                key={p.id}
                                                className="flex gap-2 leading-relaxed"
                                            >
                                                <span
                                                    className="mt-2 size-1 shrink-0 rounded-full bg-amber-500/80 dark:bg-amber-400/80"
                                                    aria-hidden
                                                />
                                                <span>{p.summary}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="flex flex-wrap gap-2">
                                        {financialPendency ? (
                                            <Button
                                                type="button"
                                                className="bg-amber-900 text-amber-50 hover:bg-amber-900/90 dark:bg-amber-100 dark:text-amber-950 dark:hover:bg-amber-100/90"
                                                onClick={() =>
                                                    setNegotiationOpen(true)
                                                }
                                            >
                                                Negociar
                                            </Button>
                                        ) : null}

                                        {hasBlockingFinancialPendency ? (
                                            artifacts ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="border-amber-200/80 bg-amber-100/80 text-amber-950 dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-50"
                                                >
                                                    Acordo gerado
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="secondary"
                                                    className="border-amber-200/80 bg-amber-100/80 text-amber-950 dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-50"
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
                                                        className="border-amber-200/80 bg-amber-100/80 text-amber-950 dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-50"
                                                    >
                                                        Quebra solicitada
                                                        (aguardando gestor)
                                                    </Badge>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="border-amber-300/80 bg-transparent text-amber-950 hover:bg-amber-100/60 dark:border-amber-700 dark:text-amber-50 dark:hover:bg-amber-900/40"
                                                        onClick={
                                                            simulateApproveOverride
                                                        }
                                                    >
                                                        Simular aprovação
                                                    </Button>
                                                </>
                                            ) : overrideStatus ===
                                              'approved' ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="border-amber-200/80 bg-amber-100/80 text-amber-950 dark:border-amber-800 dark:bg-amber-900/60 dark:text-amber-50"
                                                >
                                                    Quebra autorizada
                                                </Badge>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="border-amber-300/80 bg-transparent text-amber-950 hover:bg-amber-100/60 dark:border-amber-700 dark:text-amber-50 dark:hover:bg-amber-900/40"
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

                {hasStudent ? (
                <Card className="box-content overflow-hidden border-0 shadow-none">
                    <CardContent className="p-0">
                        <div className="px-6 pt-6">
                            <div
                                className="flex flex-wrap gap-8 border-b border-border"
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
                                    aria-selected={activeTab === 'requested'}
                                    onClick={() => setActiveTab('requested')}
                                    className={cn(
                                        'flex items-center gap-2 border-b-2 pb-3 text-sm transition-colors',
                                        activeTab === 'requested'
                                            ? 'border-foreground font-semibold text-foreground'
                                            : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    Processos solicitados
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
                        </div>

                        {activeTab === 'catalog' ? (
                            <div className="flex flex-col gap-3 border-b border-border/80 bg-muted/25 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
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
                                <div className="relative w-full sm:max-w-xs sm:shrink-0">
                                    <Search
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <Input
                                        value={filterText}
                                        onChange={(e) =>
                                            setFilterText(e.target.value)
                                        }
                                        placeholder="Filtrar serviços…"
                                        className="h-9 border-border/80 bg-background pl-9 shadow-none"
                                    />
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
                                                        className={
                                                            'cursor-pointer transition-colors hover:bg-accent/20 ' +
                                                            (selectedRequirementId ===
                                                            r.id
                                                                ? 'ring-2 ring-ring'
                                                                : '')
                                                        }
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
                                                        <CardContent className="pt-0">
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
                                                        className={
                                                            'cursor-pointer transition-colors hover:bg-accent/20 ' +
                                                            (selectedRequirementId ===
                                                            r.id
                                                                ? 'ring-2 ring-ring'
                                                                : '')
                                                        }
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
                                                        <CardContent className="pt-0">
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
                                                            className={
                                                                'cursor-pointer transition-colors hover:bg-accent/20 ' +
                                                                (selectedRequirementId ===
                                                                r.id
                                                                    ? 'ring-2 ring-ring'
                                                                    : '')
                                                            }
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
                                                            <CardContent className="pt-0 flex items-center justify-between gap-2">
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
            </div>

            <Sheet open={negotiationOpen} onOpenChange={setNegotiationOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 p-0 sm:max-w-md"
                >
                    <SheetHeader className="space-y-3 border-b px-6 pt-6 pr-12 pb-5 text-left">
                        <SheetTitle className="text-lg font-semibold">
                            Simulação de negociação
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Escolha uma condição de pagamento para gerar boleto,
                            PIX e contrato de demonstração.
                        </SheetDescription>
                        <p className="text-sm text-muted-foreground">
                            <span className="text-foreground/90">
                                {searchResult?.student?.name ?? 'Aluno'}
                            </span>
                            <span className="mx-1.5 text-foreground/35">·</span>
                            <span>
                                Débito total:{' '}
                                <strong className="font-semibold text-destructive">
                                    {formatBrl(negotiationDebtTotal)}
                                </strong>
                            </span>
                        </p>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            Para prosseguir com a abertura do processo, é
                            necessário regularizar sua situação financeira.
                            Selecione uma das condições especiais abaixo:
                        </p>
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

