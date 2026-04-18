import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as flowsIndex } from '@/routes/flows';
import type { BreadcrumbItem } from '@/types';

type Section = {
    heading: string;
    lines: string[];
};

export default function FlowsShow({
    run_id,
    workflow_name,
    iniciada_por_label,
    finished_at,
    sections,
}: {
    run_id: number;
    workflow_name: string;
    iniciada_por_label: string;
    finished_at: string | null;
    sections: Section[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Processos', href: flowsIndex() },
        { title: 'Resumo da execução', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${workflow_name} · Resumo`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {workflow_name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Execução #{run_id} · Iniciada por {iniciada_por_label}
                        {finished_at ? (
                            <>
                                {' '}
                                · Concluída em{' '}
                                {new Date(finished_at).toLocaleString('pt-PT')}
                            </>
                        ) : null}
                    </p>
                </div>

                <div className="space-y-4">
                    {sections.map((section) => (
                        <Card key={section.heading}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">
                                    {section.heading}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                    {section.lines.map((line) => (
                                        <li key={line}>{line}</li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
