function getCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    ).toString();
}

export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<{ ok: true; data: T } | { ok: false; status: number; data: unknown }> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    const data: unknown = await res.json().catch(() => ({}));
    if (!res.ok) {
        return { ok: false, status: res.status, data };
    }

    return { ok: true, data: data as T };
}

export async function patchJson<T>(
    url: string,
    body: unknown,
): Promise<{ ok: true; data: T } | { ok: false; status: number; data: unknown }> {
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    const data: unknown = await res.json().catch(() => ({}));
    if (!res.ok) {
        return { ok: false, status: res.status, data };
    }

    return { ok: true, data: data as T };
}
