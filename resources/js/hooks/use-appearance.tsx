import { usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';
import type { SharedData } from '@/types';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookieValue = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;
    const part = document.cookie
        .split('; ')
        .find((row) => row.startsWith(prefix));
    if (!part) {
        return null;
    }

    return decodeURIComponent(part.slice(prefix.length));
};

const storageKey = (userId: string | undefined | null): string =>
    userId ? `appearance:${userId}` : 'appearance';

const isValidAppearance = (value: string | null): value is Appearance =>
    value === 'light' || value === 'dark' || value === 'system';

function readAppearanceFromJsonCookie(userId: string): Appearance | null {
    const existing = getCookieValue('appearance');
    if (!existing?.startsWith('{')) {
        return null;
    }

    try {
        const parsed = JSON.parse(existing) as Record<string, unknown>;
        const v = parsed[userId];

        return typeof v === 'string' && isValidAppearance(v) ? v : null;
    } catch {
        return null;
    }
}

function readStoredAppearance(userId: string | undefined | null): Appearance {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const key = storageKey(userId);
    let raw = localStorage.getItem(key);

    if (!raw && userId) {
        const fromCookie = readAppearanceFromJsonCookie(userId);
        if (fromCookie !== null) {
            localStorage.setItem(key, fromCookie);
            raw = fromCookie;
        }
    }

    if (!raw && userId) {
        const plain = getCookieValue('appearance');
        if (plain && !plain.startsWith('{') && isValidAppearance(plain)) {
            raw = plain;
            localStorage.setItem(key, plain);
        }
    }

    if (!raw && !userId) {
        const plain = getCookieValue('appearance');
        if (plain && !plain.startsWith('{') && isValidAppearance(plain)) {
            raw = plain;
            localStorage.setItem(key, plain);
        }
    }

    if (isValidAppearance(raw)) {
        return raw;
    }

    return 'system';
}

function mergeAppearanceCookie(
    userId: string | undefined | null,
    mode: Appearance,
): void {
    if (!userId) {
        setCookie('appearance', mode);

        return;
    }

    const existing = getCookieValue('appearance');
    let map: Record<string, string> = {};

    if (existing && existing.startsWith('{')) {
        try {
            const parsed = JSON.parse(existing) as unknown;
            if (
                parsed !== null &&
                typeof parsed === 'object' &&
                !Array.isArray(parsed)
            ) {
                map = { ...parsed } as Record<string, string>;
            }
        } catch {
            map = {};
        }
    }

    map[userId] = mode;
    setCookie('appearance', JSON.stringify(map));
}

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

function getInitialUserIdFromPagePayload(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const script = document.querySelector(
        'script[type="application/json"][data-page]',
    );
    if (!script?.textContent) {
        return null;
    }

    try {
        const page = JSON.parse(script.textContent) as {
            props?: { auth?: { user?: { id?: string } | null } };
        };

        const id = page.props?.auth?.user?.id;

        return typeof id === 'string' ? id : null;
    } catch {
        return null;
    }
}

function syncAppearanceForUser(userId: string | undefined | null): void {
    currentAppearance = readStoredAppearance(userId);
    const key = storageKey(userId);
    localStorage.setItem(key, currentAppearance);
    applyTheme(currentAppearance);
    notify();
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const userId = getInitialUserIdFromPagePayload();
    syncAppearanceForUser(userId);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
    const { auth } = usePage<SharedData>().props;
    const userId = auth.user?.id;

    useEffect(() => {
        syncAppearanceForUser(userId);
    }, [userId]);

    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;
        const key = storageKey(userId);
        localStorage.setItem(key, mode);
        mergeAppearanceCookie(userId, mode);
        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
