import { Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type AuditRunRow = {
    id: number;
    workflow_id: number;
    workflow_name: string;
    status: string;
    status_label: string;
    created_at: string | null;
    iniciada_por_label: string;
    resume_url: string | null;
    view_url: string | null;
    error_message: string | null;
};

type StatusFilterId = 'all' | 'in_progress' | 'completed' | 'failed' | 'cancelled';

const STATUS_FILTERS: { id: StatusFilterId; label: string }[] = [
    { id: 'all', label: 'Todas' },
    { id: 'in_progress', label: 'Em curso' },
    { id: 'completed', label: 'Concluídas' },
    { id: 'failed', label: 'Com falha' },
    { id: 'cancelled', label: 'Canceladas' },
];

function startOfLocalDay(d: Date): Date {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
}

function dayKey(iso: string | null): string {
    if (!iso) {
        return '_nodate';
    }
    return iso.slice(0, 10);
}

function groupLabelForDayKey(key: string): string {
    if (key === '_nodate') {
        return 'Sem data';
    }
    const d = new Date(`${key}T12:00:00`);
    const today = startOfLocalDay(new Date());
    const day = startOfLocalDay(d);
    const diffMs = today.getTime() - day.getTime();
    const diffDays = Math.round(diffMs / 86_400_000);
    if (diffDays === 0) {
        return 'Hoje';
    }
    if (diffDays === 1) {
        return 'Ontem';
    }
    const formatted = d.toLocaleDateString('pt-PT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

function sortDayKeys(keys: string[]): string[] {
    return [...keys].sort((a, b) => {
        if (a === '_nodate') {
            return 1;
        }
        if (b === '_nodate') {
            return -1;
        }
        return b.localeCompare(a);
    });
}

function initialsFromName(name: string): string {
    const t = name.trim();
    if (t === '' || t === '—') {
        return '?';
    }
    const parts = t.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (
            (parts[0][0] ?? '') + (parts[parts.length - 1][0] ?? '')
        ).toUpperCase();
    }
    return t.slice(0, 2).toUpperCase();
}

function matchesStatusFilter(
    status: string,
    filter: StatusFilterId,
): boolean {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'in_progress') {
        return (
            status === 'waiting' ||
            status === 'running' ||
            status === 'pending'
        );
    }
    if (filter === 'completed') {
        return status === 'completed';
    }
    if (filter === 'failed') {
        return status === 'failed';
    }
    if (filter === 'cancelled') {
        return status === 'cancelled';
    }
    return true;
}

function escapeCsvCell(value: string): string {
    if (value.includes(',') || value.includes('"') || value.includes('\n')) {
        return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
}

function exportRunsCsv(rows: AuditRunRow[]): void {
    const headers = [
        'id',
        'processo',
        'estado',
        'iniciada_por',
        'criada_em',
        'erro',
    ];
    const lines = rows.map((r) =>
        [
            String(r.id),
            escapeCsvCell(r.workflow_name),
            escapeCsvCell(r.status_label),
            escapeCsvCell(r.iniciada_por_label),
            r.created_at ?? '',
            escapeCsvCell(r.error_message ?? ''),
        ].join(','),
    );
    const BOM = '\uFEFF';
    const csv = [headers.join(','), ...lines].join('\n');
    const blob = new Blob([BOM + csv], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `execucoes-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function RunStatusBadge({ status, label }: { status: string; label: string }) {
    if (status === 'failed') {
        return <Badge variant="destructive">{label}</Badge>;
    }
    const palette: Record<string, string> = {
        completed:
            'border-emerald-500/35 bg-emerald-500/10 text-emerald-900 dark:text-emerald-300',
        cancelled:
            'border-muted-foreground/25 bg-muted/80 text-muted-foreground',
        waiting:
            'border-sky-500/35 bg-sky-500/10 text-sky-900 dark:text-sky-300',
        running:
            'border-sky-500/35 bg-sky-500/10 text-sky-900 dark:text-sky-300',
        pending:
            'border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-200',
    };
    const cls = palette[status] ?? 'bg-muted/50 text-foreground';
    return (
        <Badge variant="outline" className={cn('font-medium', cls)}>
            {label}
        </Badge>
    );
}

export function RecentExecutionsAudit({
    runs,
    isOrgWide,
}: {
    runs: AuditRunRow[];
    isOrgWide: boolean;
}) {
    const [statusFilter, setStatusFilter] = useState<StatusFilterId>('all');
    const [search, setSearch] = useState('');

    const filteredRuns = useMemo(() => {
        const q = search.trim().toLowerCase();
        return runs.filter((run) => {
            if (!matchesStatusFilter(run.status, statusFilter)) {
                return false;
            }
            if (q === '') {
                return true;
            }
            const hay = [
                run.workflow_name,
                run.iniciada_por_label,
                run.status_label,
                run.error_message ?? '',
                String(run.id),
            ]
                .join(' ')
                .toLowerCase();
            return hay.includes(q);
        });
    }, [runs, statusFilter, search]);

    const groups = useMemo(() => {
        const sorted = [...filteredRuns].sort((a, b) => {
            const ta = a.created_at
                ? new Date(a.created_at).getTime()
                : 0;
            const tb = b.created_at
                ? new Date(b.created_at).getTime()
                : 0;
            return tb - ta;
        });
        const map = new Map<string, AuditRunRow[]>();
        for (const run of sorted) {
            const key = dayKey(run.created_at);
            const list = map.get(key) ?? [];
            list.push(run);
            map.set(key, list);
        }
        return sortDayKeys([...map.keys()]).map((key) => ({
            key,
            label: groupLabelForDayKey(key),
            runs: map.get(key) ?? [],
        }));
    }, [filteredRuns]);

    const totalListed = runs.length;

    return (
        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card text-card-foreground shadow-sm dark:border-sidebar-border">
            <div className="flex flex-col gap-4 border-b p-6 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                <div className="space-y-1">
                    <h2 className="text-lg font-semibold tracking-tight">
                        Registo de execuções
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {filteredRuns.length === totalListed
                            ? `${filteredRuns.length} execução${filteredRuns.length === 1 ? '' : 'es'}`
                            : `${filteredRuns.length} de ${totalListed} execução${totalListed === 1 ? '' : 'es'} após filtro`}
                        {isOrgWide
                            ? ' — todas as contas'
                            : ' — as tuas execuções'}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="shrink-0 gap-2"
                    disabled={filteredRuns.length === 0}
                    onClick={() => exportRunsCsv(filteredRuns)}
                >
                    <Download className="size-4" aria-hidden />
                    Exportar CSV
                </Button>
            </div>

            <div className="space-y-4 border-b px-6 py-4">
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Filtrar por processo, utilizador, estado ou ID…"
                        className="h-10 bg-background/80 pl-9"
                        aria-label="Filtrar execuções"
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    {STATUS_FILTERS.map((f) => {
                        const active = statusFilter === f.id;
                        return (
                            <button
                                key={f.id}
                                type="button"
                                onClick={() => setStatusFilter(f.id)}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'border-border bg-background text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                )}
                            >
                                {f.label}
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                    <span className="font-medium text-foreground/80">
                        Legenda:
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="size-1.5 rounded-full bg-sky-500" />
                        Em curso
                    </span>
                    <span className="text-muted-foreground/50">·</span>
                    <span className="inline-flex items-center gap-1">
                        <span className="size-1.5 rounded-full bg-emerald-500" />
                        Concluída
                    </span>
                    <span className="text-muted-foreground/50">·</span>
                    <span className="inline-flex items-center gap-1">
                        <span className="size-1.5 rounded-full bg-destructive" />
                        Falhou
                    </span>
                    <span className="text-muted-foreground/50">·</span>
                    <span className="inline-flex items-center gap-1">
                        <span className="size-1.5 rounded-full bg-muted-foreground" />
                        Outros
                    </span>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40">
                            <th className="px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Hora
                            </th>
                            <th className="px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Utilizador
                            </th>
                            <th className="px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Estado
                            </th>
                            <th className="px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Processo
                            </th>
                            <th className="px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Ref.
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Acções
                            </th>
                        </tr>
                    </thead>
                    {filteredRuns.length === 0 ? (
                        <tbody>
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    {runs.length === 0
                                        ? 'Ainda não há execuções listadas.'
                                        : 'Nenhuma execução corresponde ao filtro.'}
                                </td>
                            </tr>
                        </tbody>
                    ) : (
                        groups.map((group) => (
                            <tbody key={group.key}>
                                <tr className="bg-muted/30">
                                    <td
                                        colSpan={6}
                                        className="px-4 py-2.5 text-xs font-medium text-muted-foreground"
                                    >
                                        <span className="text-foreground">
                                            {group.label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {group.runs.length}{' '}
                                            {group.runs.length === 1
                                                ? 'execução'
                                                : 'execuções'}
                                        </span>
                                    </td>
                                </tr>
                                {group.runs.map((run) => (
                                    <tr
                                        key={run.id}
                                        className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/25"
                                    >
                                        <td className="px-4 py-3 align-middle tabular-nums">
                                            <span className="font-mono text-[13px] text-foreground">
                                                {run.created_at
                                                    ? new Date(
                                                          run.created_at,
                                                      ).toLocaleTimeString(
                                                          'pt-PT',
                                                          {
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                              second: '2-digit',
                                                              hour12: false,
                                                          },
                                                      )
                                                    : '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 align-middle">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9 border border-border/80">
                                                    <AvatarFallback className="text-xs font-medium">
                                                        {initialsFromName(
                                                            run.iniciada_por_label,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium text-foreground">
                                                        {
                                                            run.iniciada_por_label
                                                        }
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        Iniciador
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-middle">
                                            <RunStatusBadge
                                                status={run.status}
                                                label={run.status_label}
                                            />
                                        </td>
                                        <td className="max-w-[280px] px-4 py-3 align-middle">
                                            <div className="truncate font-medium text-foreground">
                                                {run.workflow_name}
                                            </div>
                                            {run.error_message ? (
                                                <div className="mt-1 line-clamp-2 text-xs text-destructive">
                                                    {run.error_message}
                                                </div>
                                            ) : (
                                                <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                    Execução de workflow
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-middle">
                                            <span className="font-mono text-xs text-muted-foreground">
                                                #{run.id}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right align-middle">
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {run.resume_url ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                run.resume_url
                                                            }
                                                        >
                                                            Continuar
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {run.view_url ? (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        className="h-8"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                run.view_url
                                                            }
                                                        >
                                                            Resumo
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {!run.resume_url &&
                                                !run.view_url ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        ))
                    )}
                </table>
            </div>

            <div className="border-t bg-muted/20 px-6 py-3 text-xs text-muted-foreground">
                {filteredRuns.length === 0 ? (
                    <span>Nada a mostrar.</span>
                ) : (
                    <span>
                        A mostrar {filteredRuns.length} de {totalListed}{' '}
                        {totalListed === 1 ? 'execução' : 'execuções'}
                    </span>
                )}
            </div>
        </div>
    );
}
