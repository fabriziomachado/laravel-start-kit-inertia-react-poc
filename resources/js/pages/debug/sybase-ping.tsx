import { Head } from '@inertiajs/react';

type SybaseErrorPayload = {
    errorCode: number;
    errorMessage: string;
} | null;

type Props = {
    rows: unknown[] | null;
    sybaseError: SybaseErrorPayload;
};

export default function SybasePingDebug({ rows, sybaseError }: Props) {
    return (
        <>
            <Head title="Sybase ping (debug)" />
            <div className="min-h-screen bg-neutral-50 p-6 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
                <h1 className="mb-4 text-xl font-semibold">
                    Sybase RPC — debug
                </h1>

                {sybaseError ? (
                    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
                        <p className="font-medium">
                            ProcedureExecutionException
                        </p>
                        <dl className="mt-2 grid gap-1 text-sm">
                            <dt className="text-neutral-600 dark:text-neutral-400">
                                errorCode
                            </dt>
                            <dd className="font-mono">
                                {sybaseError.errorCode}
                            </dd>
                            <dt className="text-neutral-600 dark:text-neutral-400">
                                errorMessage
                            </dt>
                            <dd className="font-mono">
                                {sybaseError.errorMessage ?? '—'}
                            </dd>
                        </dl>
                    </div>
                ) : null}

                <p className="mb-2 text-sm text-neutral-600 dark:text-neutral-400">
                    Resultado de{' '}
                    <code className="rounded bg-neutral-200 px-1 py-0.5 dark:bg-neutral-800">
                        prss_login_r01
                    </code>{' '}
                    (raw)
                </p>
                <pre className="max-h-[70vh] overflow-auto rounded-lg border border-neutral-200 bg-white p-4 text-xs leading-relaxed dark:border-neutral-800 dark:bg-neutral-900">
                    {rows === null ? '—' : JSON.stringify(rows, null, 2)}
                </pre>
            </div>
        </>
    );
}
