import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Formulário concluído', href: '#' },
];

type Props = {
    run_id: number;
};

export default function WorkflowFormDone({ run_id }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formulário concluído" />
            <div className="mx-auto max-w-lg space-y-4 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">Formulário concluído</h1>
                <p className="text-muted-foreground text-sm">
                    A execução do workflow #{run_id} foi concluída.
                </p>
                <Button asChild variant="secondary">
                    <Link href={dashboard()}>Voltar ao painel</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
