import { Head, Link, router, usePage } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import { useCallback, useState } from 'react';
import { RecentExecutionsAudit } from '@/components/flows/recent-executions-audit';
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
import { index as flowsIndex, intake as flowsIntake } from '@/routes/flows';
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
    const { flows_error, flows_success, auth } = usePage<SharedData>().props;
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
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Requerimentos
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Escolha um requerimento para iniciar ou consulte as
                                execuções recentes.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={flowsIntake.url()}>Novo requerimento</Link>
                        </Button>
                    </div>
                </div>

                {flows_error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Requerimentos</AlertTitle>
                        <AlertDescription>{flows_error}</AlertDescription>
                    </Alert>
                ) : null}
                {flows_success ? (
                    <Alert>
                        <AlertTitle>Requerimentos</AlertTitle>
                        <AlertDescription>{flows_success}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="hidden space-y-3">
                    <h2 className="text-lg font-medium tracking-tight">
                        Requerimentos disponíveis
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
                    <RecentExecutionsAudit
                        runs={runs}
                        isOrgWide={Boolean(auth.user?.is_admin)}
                    />
                </section>
            </div>
        </AppLayout>
    );
}
