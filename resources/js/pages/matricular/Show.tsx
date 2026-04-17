import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard, matricular } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Section = {
    heading: string;
    lines: string[];
};

type Props = {
    run_id: number;
    workflow_name: string;
    iniciada_por_label: string;
    finished_at: string | null;
    sections: Section[];
};

function formatFinishedAt(iso: string | null): string {
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

export default function MatricularShow({
    run_id,
    workflow_name,
    iniciada_por_label,
    finished_at,
    sections,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Matricular', href: matricular.url() },
        { title: `Instância #${run_id}`, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Matrícula #${run_id}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Resumo da matrícula</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{workflow_name}</p>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Execução #{run_id} — apenas leitura. Iniciada por {iniciada_por_label}. Concluída em{' '}
                            {formatFinishedAt(finished_at)}.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={matricular.url()}>Voltar à lista</Link>
                    </Button>
                </div>

                <Separator />

                {sections.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Não há dados guardados para mostrar nesta execução.
                    </p>
                ) : (
                    <div className="space-y-8">
                        {sections.map((section, si) => (
                            <section key={`${section.heading}-${si}`} className="space-y-3">
                                <h2 className="text-lg font-medium tracking-tight">{section.heading}</h2>
                                <ul className="border-border bg-muted/20 space-y-1 rounded-lg border px-4 py-3 text-sm">
                                    {section.lines.map((line, li) => (
                                        <li key={`${si}-${li}`} className="leading-relaxed">
                                            {line}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
