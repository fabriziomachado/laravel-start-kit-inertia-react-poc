import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { impersonate } from '@/routes';
import { index as usersIndex } from '@/routes/users';
import type { BreadcrumbItem, SharedData, User } from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: User[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilizadores',
        href: usersIndex(),
    },
];

export default function UsersIndex({ users }: { users: PaginatedUsers }) {
    const { auth, impersonating } = usePage<SharedData>().props;
    const currentUserId = auth.user?.id;
    const canShowImpersonateButton =
        impersonating !== true &&
        auth.user?.is_admin === true &&
        currentUserId !== undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Utilizadores" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Utilizadores
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Lista de contas registadas. Apenas administradores podem
                        impersonar outro utilizador.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nome</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Admin</th>
                                <th className="px-4 py-3 font-medium">
                                    Criado
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b border-sidebar-border/50 last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {row.name}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {row.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.is_admin ? 'Sim' : 'Não'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {row.created_at}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canShowImpersonateButton &&
                                        currentUserId !== row.id ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={impersonate.get({
                                                        id: row.id,
                                                    })}
                                                    data-test={`impersonate-user-${row.id}`}
                                                >
                                                    Logar como
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <nav
                        className="flex flex-wrap items-center gap-2"
                        aria-label="Paginação"
                    >
                        {users.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={
                                        link.active
                                            ? 'font-semibold underline'
                                            : 'text-muted-foreground hover:underline'
                                    }
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Link>
                            ) : (
                                <span
                                    key={index}
                                    className="text-muted-foreground"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
