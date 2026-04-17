import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { store as matricularStore } from '@/routes/matricular';
import type { BreadcrumbItem, SharedData } from '@/types';
import { cn } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Matricular', href: '#' },
];

type RunRow = {
    id: number;
    status: string;
    status_label: string;
    created_at: string | null;
    iniciada_por_label: string;
    resume_url: string | null;
    view_url: string | null;
    error_message: string | null;
};

type Props = {
    workflow_name: string;
    runs: RunRow[];
};

function calendarDayKey(iso: string | null): string {
    if (!iso) {
        return 'sem-data';
    }
    const d = new Date(iso);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function localCalendarKey(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function humanDayLabel(key: string): string {
    if (key === 'sem-data') {
        return 'Sem data';
    }
    const todayKey = localCalendarKey(new Date());
    const y = new Date();
    y.setDate(y.getDate() - 1);
    const yesterdayKey = localCalendarKey(y);
    if (key === todayKey) {
        return 'Hoje';
    }
    if (key === yesterdayKey) {
        return 'Ontem';
    }
    const [yy, mm, dd] = key.split('-').map(Number);

    return new Intl.DateTimeFormat('pt-PT', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(yy, mm - 1, dd));
}

function formatTimeHms(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(new Date(iso));
    } catch {
        return '—';
    }
}

function initialsFromName(name: string): string {
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    return name.replace(/[^\p{L}\d]/gu, '').slice(0, 2).toUpperCase() || '—';
}

function statusBadgeClass(status: string): string {
    return cn(
        'rounded-full border px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide',
        status === 'completed' &&
            'border-emerald-200/80 bg-emerald-50 text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/35 dark:text-emerald-100',
        (status === 'waiting' || status === 'running') &&
            'border-sky-200/80 bg-sky-50 text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/35 dark:text-sky-100',
        status === 'failed' &&
            'border-red-200/80 bg-red-50 text-red-950 dark:border-red-900/40 dark:bg-red-950/35 dark:text-red-100',
        status === 'cancelled' &&
            'border-border bg-muted/60 text-muted-foreground',
        status === 'pending' &&
            'border-amber-200/80 bg-amber-50 text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/35 dark:text-amber-100',
    );
}

type DayGroup = { dayKey: string; dayLabel: string; rows: RunRow[] };

function groupRunsByDayDescending(rows: RunRow[]): DayGroup[] {
    const map = new Map<string, RunRow[]>();
    for (const r of rows) {
        const k = calendarDayKey(r.created_at);
        if (!map.has(k)) {
            map.set(k, []);
        }
        map.get(k)!.push(r);
    }
    const keys = [...map.keys()].sort((a, b) => b.localeCompare(a));

    return keys.map((dayKey) => ({
        dayKey,
        dayLabel: humanDayLabel(dayKey),
        rows: map.get(dayKey)!,
    }));
}

export default function MatricularIndex({ workflow_name, runs }: Props) {
    const { matricula_error, matricula_success } = usePage<SharedData>().props;
    const [processing, setProcessing] = useState(false);
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    const filteredRuns = useMemo(() => {
        const q = query.trim().toLowerCase();
        return runs.filter((r) => {
            if (statusFilter !== 'all' && r.status !== statusFilter) {
                return false;
            }
            if (!q) {
                return true;
            }
            return (
                String(r.id).includes(q) ||
                r.iniciada_por_label.toLowerCase().includes(q) ||
                r.status_label.toLowerCase().includes(q) ||
                (r.error_message?.toLowerCase().includes(q) ?? false)
            );
        });
    }, [runs, query, statusFilter]);

    const groups = useMemo(() => groupRunsByDayDescending(filteredRuns), [filteredRuns]);

    const handleStart = (): void => {
        if (
            !window.confirm(
                'Iniciar uma nova matrícula? Só avance se quiser mesmo começar um novo pedido; evita deixar matrículas a meio sem necessidade.',
            )
        ) {
            return;
        }
        setProcessing(true);
        router.post(matricularStore.url(), {}, {
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const colSpan = 5;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Matricular" />
            <div className="mx-auto flex max-w-5xl flex-col gap-8 p-4 lg:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Matricular</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{workflow_name}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" disabled={processing} onClick={handleStart}>
                            {processing ? <Spinner /> : null}
                            {processing ? ' A iniciar…' : 'Iniciar nova matrícula'}
                        </Button>
                    </div>
                </div>

                {matricula_success ? (
                    <Alert>
                        <AlertTitle>Matrícula</AlertTitle>
                        <AlertDescription>{matricula_success}</AlertDescription>
                    </Alert>
                ) : null}

                {matricula_error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Matrícula</AlertTitle>
                        <AlertDescription>{matricula_error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="overflow-hidden border-border/80 py-0 shadow-sm">
                    <div className="border-border/60 space-y-1 border-b px-6 py-5">
                        <h2 className="text-lg font-semibold tracking-tight">Instâncias do fluxo</h2>
                        <p className="text-muted-foreground text-sm">
                            {runs.length} execuç{runs.length === 1 ? 'ão' : 'ões'} registada{runs.length === 1 ? '' : 's'}
                            {filteredRuns.length !== runs.length
                                ? ` · ${filteredRuns.length} com os filtros actuais`
                                : ''}
                            .
                        </p>
                    </div>

                    <div className="border-border/60 flex flex-col gap-3 border-b px-6 py-4 sm:flex-row sm:items-center">
                        <div className="relative min-w-0 flex-1">
                            <Search
                                className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 opacity-70"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                placeholder="Pesquisar por #, nome ou estado…"
                                value={query}
                                onChange={(e) => {
                                    setQuery(e.target.value);
                                }}
                                className="h-10 bg-background/80 pl-9"
                                aria-label="Pesquisar instâncias"
                            />
                        </div>
                        <select
                            value={statusFilter}
                            onChange={(e) => {
                                setStatusFilter(e.target.value);
                            }}
                            className="border-input bg-background text-foreground h-10 w-full shrink-0 rounded-md border px-3 text-sm shadow-xs sm:w-44"
                            aria-label="Filtrar por estado"
                        >
                            <option value="all">Todos os estados</option>
                            <option value="waiting">Em curso</option>
                            <option value="running">A processar</option>
                            <option value="completed">Concluída</option>
                            <option value="failed">Falhou</option>
                            <option value="cancelled">Cancelada</option>
                            <option value="pending">Pendente</option>
                        </select>
                    </div>

                    {runs.length === 0 ? (
                        <p className="text-muted-foreground px-6 py-12 text-center text-sm">
                            Ainda não há instâncias registadas. Utilize o botão acima para começar.
                        </p>
                    ) : filteredRuns.length === 0 ? (
                        <p className="text-muted-foreground px-6 py-12 text-center text-sm">
                            Nenhum resultado com os filtros actuais.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b">
                                        <th className="px-6 py-3 text-[11px] font-medium tracking-wider uppercase">
                                            Hora
                                        </th>
                                        <th className="px-6 py-3 text-[11px] font-medium tracking-wider uppercase">
                                            Iniciada por
                                        </th>
                                        <th className="px-6 py-3 text-[11px] font-medium tracking-wider uppercase">
                                            Estado
                                        </th>
                                        <th className="px-6 py-3 text-[11px] font-medium tracking-wider uppercase">
                                            Instância
                                        </th>
                                        <th className="px-6 py-3 text-right text-[11px] font-medium tracking-wider uppercase">
                                            Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {groups.map((g) => (
                                        <Fragment key={g.dayKey}>
                                            <tr className="bg-muted/40">
                                                <td
                                                    colSpan={colSpan}
                                                    className="text-muted-foreground px-6 py-2.5 text-xs font-medium"
                                                >
                                                    <div className="flex items-center justify-between gap-3">
                                                        <span>{g.dayLabel}</span>
                                                        <span className="font-normal tabular-nums opacity-80">
                                                            {g.rows.length}{' '}
                                                            {g.rows.length === 1 ? 'execução' : 'execuções'}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                            {g.rows.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className="hover:bg-muted/25 transition-colors"
                                                >
                                                    <td className="text-muted-foreground px-6 py-3.5 align-middle font-mono text-xs tabular-nums">
                                                        {formatTimeHms(row.created_at)}
                                                    </td>
                                                    <td className="px-6 py-3.5 align-middle">
                                                        <div className="flex items-center gap-3">
                                                            <Avatar className="size-9 border-0 shadow-none ring-1 ring-border/50">
                                                                <AvatarFallback className="bg-muted/70 text-[11px] font-semibold">
                                                                    {initialsFromName(row.iniciada_por_label)}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div className="min-w-0">
                                                                <p className="truncate font-medium">
                                                                    {row.iniciada_por_label}
                                                                </p>
                                                                {row.iniciada_por_label !== '—' ? (
                                                                    <p className="text-muted-foreground truncate text-xs">
                                                                        Candidato
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-3.5 align-middle">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(statusBadgeClass(row.status))}
                                                        >
                                                            {row.status_label}
                                                        </Badge>
                                                        {row.error_message ? (
                                                            <p
                                                                className="text-muted-foreground mt-1.5 max-w-[14rem] truncate text-xs"
                                                                title={row.error_message}
                                                            >
                                                                {row.error_message}
                                                            </p>
                                                        ) : null}
                                                    </td>
                                                    <td className="px-6 py-3.5 align-middle">
                                                        <p className="font-medium tabular-nums">#{row.id}</p>
                                                        <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs leading-snug">
                                                            {workflow_name}
                                                        </p>
                                                    </td>
                                                    <td className="px-6 py-3.5 align-middle text-right">
                                                        <div className="flex flex-wrap justify-end gap-2">
                                                            {row.resume_url ? (
                                                                <Button variant="secondary" size="sm" asChild>
                                                                    <Link href={row.resume_url}>Continuar</Link>
                                                                </Button>
                                                            ) : null}
                                                            {row.view_url ? (
                                                                <Button variant="outline" size="sm" asChild>
                                                                    <Link href={row.view_url}>Ver</Link>
                                                                </Button>
                                                            ) : null}
                                                            {!row.resume_url && !row.view_url ? (
                                                                <span className="text-muted-foreground text-xs">—</span>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {filteredRuns.length > 0 ? (
                        <div className="text-muted-foreground flex flex-col gap-3 border-t px-6 py-4 text-xs sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                A mostrar {filteredRuns.length} de {runs.length} instância
                                {runs.length === 1 ? '' : 's'}
                            </p>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden />
                                    Concluída
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-1.5 shrink-0 rounded-full bg-sky-500" aria-hidden />
                                    Em curso / a processar
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-1.5 shrink-0 rounded-full bg-red-500" aria-hidden />
                                    Falhou
                                </span>
                            </div>
                        </div>
                    ) : null}
                </Card>
            </div>
        </AppLayout>
    );
}
