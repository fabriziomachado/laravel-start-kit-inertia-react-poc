export type ChoiceCardDef = {
    value: string;
    label: string;
    description?: string;
    icon?: string;
};

export type FormField = {
    key: string;
    label: string;
    type: string;
    required?: boolean;
    placeholder?: string;
    options?: string;
    choices?: ChoiceCardDef[];
    ask?: string;
    ui_hints?: { ask?: string; placeholder_question?: string; confirm_label?: string };
};

export type Step = {
    title: string;
    description?: string | null;
    submit_label: string;
    fields: FormField[];
};

export type ProgressStep = {
    node_id: number;
    label: string;
    node_key: string;
    state: 'completed' | 'current' | 'pending';
    completed_at: string | null;
    summary_lines: string[];
    description?: string | null;
    actor_name?: string | null;
};

export type ProgressPayload = {
    workflow_name: string;
    workflow_description?: string | null;
    steps: ProgressStep[];
};

export type TimelineHeadingRow = {
    type: 'heading';
    title: string;
    reactKey: string;
};

export type TimelineStepRow = {
    type: 'step';
    step: ProgressStep;
    displayIndex: number;
    reactKey: string;
};

export type ProgressSideTabId = 'details' | 'activities' | 'copilot';

export type ChatMessage = {
    role: 'assistant' | 'user';
    content: string;
    meta?: Record<string, unknown>;
};
