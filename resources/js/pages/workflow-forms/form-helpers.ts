import type { ChoiceCardDef, FormField } from './types';

export function normalizeChoices(raw: unknown): ChoiceCardDef[] {
    if (!Array.isArray(raw)) {
        return [];
    }
    const out: ChoiceCardDef[] = [];
    for (const row of raw) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        const r = row as Record<string, unknown>;
        const value = r.value;
        const label = r.label;
        if (typeof value !== 'string' || typeof label !== 'string') {
            continue;
        }
        out.push({
            value,
            label,
            description:
                typeof r.description === 'string' ? r.description : undefined,
            icon: typeof r.icon === 'string' ? r.icon : undefined,
        });
    }

    return out;
}

export function buildInitialData(
    fields: FormField[],
): Record<string, string | boolean | number> {
    const data: Record<string, string | boolean | number> = {};
    for (const f of fields) {
        if (f.type === 'boolean') {
            data[f.key] = false;
        } else if (f.type === 'number') {
            data[f.key] = '';
        } else {
            data[f.key] = '';
        }
    }

    return data;
}

export function mergeInitialData(
    fields: FormField[],
    prefill: Record<string, unknown>,
): Record<string, string | boolean | number> {
    const data = buildInitialData(fields);
    for (const f of fields) {
        if (!Object.prototype.hasOwnProperty.call(prefill, f.key)) {
            continue;
        }
        const v = prefill[f.key];
        if (f.type === 'boolean') {
            data[f.key] = v === true || v === 1 || v === '1' || v === 'true';
        } else if (f.type === 'number') {
            if (typeof v === 'number' && !Number.isNaN(v)) {
                data[f.key] = v;
            } else if (typeof v === 'string' && v !== '') {
                data[f.key] = Number(v);
            }
        } else {
            data[f.key] = v == null ? '' : String(v);
        }
    }

    return data;
}

export function parseSelectOptions(csv?: string): string[] {
    if (!csv) {
        return [];
    }

    return csv
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
}
