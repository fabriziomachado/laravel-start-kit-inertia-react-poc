import { Head, Link, router, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { store as matricularStore } from '@/routes/matricular';
import type { BreadcrumbItem, SharedData } from '@/types';
import { useState } from 'react';

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

function formatStartedAt(iso: string | null): string {
    if (!iso) {
        return '—';
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

export default function MatricularIndex({ workflow_name, runs }: Props) {
    const { matricula_error } = usePage<SharedData>().props;
    const [processing, setProcessing] = useState(false);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Matricular" />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Matricular</h1>
                    <p className="text-muted-foreground mt-1 text-sm">{workflow_name}</p>
                </div>

                {matricula_error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Matrícula</AlertTitle>
                        <AlertDescription>{matricula_error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" disabled={processing} onClick={handleStart}>
                        {processing ? <Spinner /> : null}
                        {processing ? ' A iniciar…' : 'Iniciar nova matrícula'}
                    </Button>
                </div>

                <div className="space-y-2">
                    <h2 className="text-lg font-medium tracking-tight">Instâncias deste fluxo</h2>
                    <p className="text-muted-foreground text-sm">
                        Execuções do processo de matrícula (cada linha é uma instância iniciada neste workflow).
                    </p>
                    {runs.length === 0 ? (
                        <p className="text-muted-foreground border-border rounded-lg border border-dashed p-6 text-center text-sm">
                            Ainda não há instâncias registadas. Utilize o botão acima para iniciar a primeira.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-muted/50 border-b">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">#</th>
                                        <th className="px-4 py-3 font-medium">Estado</th>
                                        <th className="px-4 py-3 font-medium">Início</th>
                                        <th className="px-4 py-3 font-medium">Iniciada por</th>
                                        <th className="px-4 py-3 text-right font-medium">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runs.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/50 last:border-0"
                                        >
                                            <td className="px-4 py-3 font-mono">{row.id}</td>
                                            <td className="px-4 py-3">
                                                <span>{row.status_label}</span>
                                                {row.error_message ? (
                                                    <p
                                                        className="text-muted-foreground mt-1 max-w-xs truncate text-xs"
                                                        title={row.error_message}
                                                    >
                                                        {row.error_message}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {formatStartedAt(row.created_at)}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {row.iniciada_por_label}
                                            </td>
                                            <td className="px-4 py-3 text-right">
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
                                                        <span className="text-muted-foreground">—</span>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
