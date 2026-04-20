import { Head, router } from '@inertiajs/react';
import { ExternalLink, LoaderCircle, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
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
    const [isGeneratingArtifacts, setIsGeneratingArtifacts] = useState(false);
    const [artifacts, setArtifacts] = useState<NegotiationArtifacts | null>(
        null,
    );
    const [financialResolved, setFinancialResolved] = useState(false);

    const [activeTab, setActiveTab] = useState<'catalog' | 'requested'>(
        'catalog',
    );
    const [filterText, setFilterText] = useState('');
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

    const hasStudent = searchResult?.student !== null && searchResult !== null;
    const pendencies = searchResult?.pendencies ?? [];
    const financialPendency = pendencies.find((p) => p.type === 'financial');
    const hasBlockingNonFinancialPendency =
        pendencies.some((p) => p.type !== 'financial') &&
        overrideStatus !== 'approved';
    const hasBlockingFinancialPendency = Boolean(financialPendency) && !financialResolved;
    const hasBlockingPendency =
        hasBlockingNonFinancialPendency || hasBlockingFinancialPendency;

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

    const negotiationOptions = useMemo(
        () => [
            {
                id: 'cash-10',
                label: 'À vista (10% desc.)',
                note: 'Recomendado',
            },
            {
                id: '3x',
                label: 'Parcelado em 3x',
                note: 'Sem juros',
            },
            {
                id: '6x',
                label: 'Parcelado em 6x',
                note: 'Com taxas',
            },
        ],
        [],
    );

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

    const popularRequirements = useMemo(() => {
        const popularSet = new Set(popularRequirementIds);
        return visibleRequirements.filter((r) => popularSet.has(r.id)).slice(0, 6);
    }, [popularRequirementIds, visibleRequirements]);

    const recentRequirements = useMemo(() => {
        const ids = readRecentRequirementIds();
        if (ids.length === 0) {
            return [];
        }

        const popularIds = new Set(popularRequirements.map((r) => r.id));
        const index = new Map(visibleRequirements.map((r) => [r.id, r]));

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
    }, [popularRequirements, visibleRequirements]);

    const catalogRequirements = useMemo(() => {
        const used = new Set<number>([
            ...popularRequirements.map((r) => r.id),
            ...recentRequirements.map((r) => r.id),
        ]);

        return visibleRequirements.filter((r) => !used.has(r.id));
    }, [popularRequirements, recentRequirements, visibleRequirements]);

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
                        Consulte o aluno e selecione o requerimento para iniciar.
                    </p>
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">
                            Pesquisa de estudante
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="flex-1">
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Código, nome ou email…"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            submit();
                                        }
                                    }}
                                />
                            </div>
                            <Button
                                type="button"
                                disabled={!canSearch}
                                onClick={submit}
                            >
                                <Search className="mr-2 size-4" aria-hidden />
                                Consultar
                            </Button>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Digite pelo menos 3 caracteres para consultar.
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
                    <Card>
                        <CardHeader className="pb-2">
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <CardTitle className="text-base">
                                        {searchResult.student.name}
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Matrícula {searchResult.student.code} ·{' '}
                                        {searchResult.student.course} ·{' '}
                                        {searchResult.student.semester} ·{' '}
                                        {searchResult.student.unit}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        {searchResult.student.status}
                                    </Badge>
                                    {dossierUrl ? (
                                        <Button variant="outline" asChild>
                                            <a
                                                href={dossierUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink
                                                    className="mr-2 size-4"
                                                    aria-hidden
                                                />
                                                Ver dossiê completo
                                            </a>
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                ) : null}

                {hasStudent && pendencies.length > 0 ? (
                    <Alert
                        variant={hasBlockingPendency ? 'destructive' : 'default'}
                    >
                        <AlertTitle>Pendências</AlertTitle>
                        <AlertDescription>
                            <div className="space-y-2">
                                <ul className="list-inside list-disc">
                                    {pendencies.map((p) => (
                                        <li key={p.id}>{p.summary}</li>
                                    ))}
                                </ul>

                                <div className="flex flex-wrap gap-2">
                                    {financialPendency ? (
                                        <Button
                                            type="button"
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
                                            <Badge variant="secondary">
                                                Negociação necessária para
                                                continuar
                                            </Badge>
                                        )
                                    ) : null}

                                    {hasBlockingNonFinancialPendency ? (
                                        overrideStatus === 'requested' ? (
                                            <>
                                                <Badge variant="secondary">
                                                    Quebra solicitada (aguardando
                                                    gestor)
                                                </Badge>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={simulateApproveOverride}
                                                >
                                                    Simular aprovação
                                                </Button>
                                            </>
                                        ) : overrideStatus === 'approved' ? (
                                            <Badge variant="secondary">
                                                Quebra autorizada
                                            </Badge>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={requestOverride}
                                            >
                                                Solicitar quebra
                                            </Button>
                                        )
                                    ) : null}
                                </div>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">
                            Seleção de requerimento
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            activeTab === 'catalog'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setActiveTab('catalog')}
                                    >
                                        Novo requerimento{' '}
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {requirements.length}
                                        </Badge>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            activeTab === 'requested'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            setActiveTab('requested')
                                        }
                                    >
                                        Processos solicitados{' '}
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {requestedProcesses.length}
                                        </Badge>
                                    </Button>
                                </div>
                                {activeTab === 'catalog' ? (
                                    <div className="w-full sm:max-w-sm">
                                        <Input
                                            value={filterText}
                                            onChange={(e) =>
                                                setFilterText(e.target.value)
                                            }
                                            placeholder="Filtrar requerimentos…"
                                        />
                                    </div>
                                ) : null}
                            </div>

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
                                <div className="space-y-6">
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

                                    {visibleRequirements.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {filterText.trim()
                                                ? 'Nenhum requerimento corresponde ao filtro.'
                                                : requirements.length === 0
                                                  ? 'Nenhum requerimento disponível.'
                                                  : null}
                                        </p>
                                    ) : null}
                                </div>
                            )}

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {selectedRequirementId ? (
                                        <>Selecionado: #{selectedRequirementId}</>
                                    ) : (
                                        <>Selecione um requerimento para iniciar.</>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    disabled={
                                        !hasStudent ||
                                        hasBlockingPendency ||
                                        selectedRequirementId === null
                                    }
                                    onClick={() =>
                                        selectedRequirementId !== null
                                            ? startRequirement(selectedRequirementId)
                                            : null
                                    }
                                >
                                    Iniciar
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Sheet open={negotiationOpen} onOpenChange={setNegotiationOpen}>
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>Negociação financeira</SheetTitle>
                        <SheetDescription>
                            Selecione uma opção para gerar boleto, PIX e contrato
                            (mock).
                        </SheetDescription>
                    </SheetHeader>

                    <div className="space-y-3 px-4">
                        {negotiationOptions.map((opt) => (
                            <Card key={opt.id}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <CardTitle className="text-base">
                                            {opt.label}
                                        </CardTitle>
                                        <Badge variant="secondary">
                                            {opt.note}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between gap-3">
                                    <div className="text-sm text-muted-foreground">
                                        Confirme para gerar os artefatos.
                                    </div>
                                    <Button
                                        type="button"
                                        disabled={isGeneratingArtifacts}
                                        onClick={() => generateArtifacts(opt.id)}
                                    >
                                        {isGeneratingArtifacts
                                            ? 'A gerar…'
                                            : 'Confirmar'}
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}

                        {artifacts ? (
                            <Card>
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

                    <SheetFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setNegotiationOpen(false)}
                        >
                            Fechar
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

