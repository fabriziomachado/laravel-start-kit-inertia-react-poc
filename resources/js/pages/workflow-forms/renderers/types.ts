import type { InertiaFormProps } from '@inertiajs/react';
import type { ProgressPayload, Step } from '../types';

export type StepRendererProps = {
    token: string;
    step: Step;
    run_id: number;
    progress: ProgressPayload;
    previous_token: string | null;
    form: InertiaFormProps<Record<string, unknown>>;
    hasChoiceCards: boolean;
};
