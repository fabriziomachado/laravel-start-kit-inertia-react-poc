import { Head, Link, router, usePage } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as flowsIndex } from '@/routes/flows';
import { store as flowsRunsStore } from '@/routes/flows/runs';
import type { BreadcrumbItem, SharedData } from '@/types';

type WorkflowRow = {
    id: number;
    name: string;
    description: string | null;
};

type RunRow = {
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Processos', href: flowsIndex() },
];

export default function FlowsIndex({
    workflows,
    runs,
}: {
    workflows: WorkflowRow[];
    runs: RunRow[];
}) {
    const { flows_error, flows_success } = usePage<SharedData>().props;
    const [startingId, setStartingId] = useState<number | null>(null);

    const startWorkflow = useCallback((workflow: WorkflowRow) => {
        if (
            !window.confirm(
                `Iniciar o processo «${workflow.name}»? Será aberto o primeiro passo.`,
            )
        ) {
            return;
        }
        setStartingId(workflow.id);
        router.post(
            flowsRunsStore.url({ workflow: workflow.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setStartingId(null),
            },
        );
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Processos" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Processos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Escolha um fluxo para iniciar ou consulte as execuções
                        recentes.
                    </p>
                </div>

                {flows_error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Processos</AlertTitle>
                        <AlertDescription>{flows_error}</AlertDescription>
                    </Alert>
                ) : null}
                {flows_success ? (
                    <Alert>
                        <AlertTitle>Processos</AlertTitle>
                        <AlertDescription>{flows_success}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="space-y-3">
                    <h2 className="text-lg font-medium tracking-tight">
                        Fluxos disponíveis
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {workflows.map((w) => (
                            <Card key={w.id} className="flex flex-col">
                                <CardHeader className="pb-2">
                                    <div className="flex items-start gap-2">
                                        <GitBranch
                                            className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <div className="min-w-0">
                                            <CardTitle className="text-base">
                                                {w.name}
                                            </CardTitle>
                                            {w.description ? (
                                                <CardDescription className="mt-1 line-clamp-3">
                                                    {w.description}
                                                </CardDescription>
                                            ) : null}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1" />
                                <CardFooter className="pt-0">
                                    <Button
                                        className="w-full sm:w-auto"
                                        disabled={startingId === w.id}
                                        onClick={() => startWorkflow(w)}
                                    >
                                        {startingId === w.id
                                            ? 'A iniciar…'
                                            : 'Iniciar'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-medium tracking-tight">
                        Execuções recentes
                    </h2>
                    <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-3 font-medium">
                                        Processo
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Iniciada por
                                    </th>
                                    <th className="px-3 py-3 text-right font-medium">
                                        Acções
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {runs.length === 0 ? (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={4}
                                        >
                                            Ainda não há execuções listadas.
                                        </td>
                                    </tr>
                                ) : (
                                    runs.map((run) => (
                                        <tr
                                            key={run.id}
                                            className="border-b border-sidebar-border/50 last:border-0"
                                        >
                                            <td className="px-3 py-3 align-top">
                                                <div className="font-medium">
                                                    {run.workflow_name}
                                                </div>
                                                {run.error_message ? (
                                                    <div className="mt-1 max-w-md text-xs text-destructive">
                                                        {run.error_message}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-3 align-top text-muted-foreground">
                                                {run.status_label}
                                            </td>
                                            <td className="px-3 py-3 align-top text-muted-foreground">
                                                {run.iniciada_por_label}
                                            </td>
                                            <td className="px-3 py-3 text-right align-top">
                                                {run.resume_url ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
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
                                                        className={
                                                            run.resume_url
                                                                ? 'ml-2'
                                                                : ''
                                                        }
                                                        asChild
                                                    >
                                                        <Link
                                                            href={run.view_url}
                                                        >
                                                            Ver resumo
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                {!run.resume_url &&
                                                !run.view_url ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
