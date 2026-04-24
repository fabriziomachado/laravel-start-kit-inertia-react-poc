import { useForm } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, ChevronUp, Pencil, SendHorizonal, Sparkles, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { postJson } from '@/lib/workflow-form-api';
import { cn } from '@/lib/utils';
import {
    aiExtract as aiExtractRoute,
    chat as chatRoute,
    submitChat as submitChatRoute,
} from '@/routes/workflow-forms';
import { edit as editChatRoute } from '@/routes/workflow-forms/chat';
import type { User } from '@/types/auth';
import type { ChatAdvancePayload } from '../Show';
import { mergeInitialData, normalizeChoices, parseSelectOptions } from '../form-helpers';
import type { ChatDraftUpdatePayload, ChatMessage, FormField, Step } from '../types';
import { WorkflowFormRendererHeader } from '../WorkflowFormRendererHeader';
import type { WorkflowInteractionMode } from './types';

const ASSISTANT_AVATAR_SRC = '/images/smartsau-mascot.png';
const ASSISTANT_NAME = 'SmartSaú';

type Props = {
    token: string;
    step: Step;
    run_id: number;
    previous_token: string | null;
    prefill: Record<string, unknown>;
    initialMessages: ChatMessage[];
    aiExtractAvailable: boolean;
    user: User | null;
    workflowName?: string | null;
    onAdvance: (next: ChatAdvancePayload) => void;
    onComplete: (redirectUrl: string) => void;
    /** Mantém mensagens e rascunho alinhados com o pai ao alternar Formulário/Chat. */
    onDraftUpdate?: (payload: ChatDraftUpdatePayload) => void;
    /** Incrementado no pai em cada submit-chat bem-sucedido; distingue avanço no chat de mudança de URL (Inertia). */
    chatAdvanceSeq: number;
    interactionMode: WorkflowInteractionMode;
    onInteractionModeChange: (mode: WorkflowInteractionMode) => void;
};

/**
 * Gera um avatar fake online determinístico para o utilizador quando este
 * não tem foto carregada. Usa o i.pravatar.cc (sem chave), seeded pelo id ou
 * e-mail para manter o mesmo rosto entre navegações.
 */
function buildFakeUserAvatar(user: User | null): string | null {
    if (!user) {
        return null;
    }
    if (user.avatar) {
        return user.avatar;
    }
    const seed = encodeURIComponent(String(user.id ?? user.email ?? user.name ?? 'guest'));

    return `https://i.pravatar.cc/150?u=${seed}`;
}

type TransitionMessage = {
    role: 'system';
    content: string;
    meta?: { at?: string };
};

type DisplayMessage = ChatMessage | TransitionMessage;

function lastAssistantMessage(messages: ChatMessage[]): ChatMessage | null {
    for (let i = messages.length - 1; i >= 0; i--) {
        const m = messages[i];
        if (m?.role === 'assistant') {
            return m;
        }
    }

    return null;
}

function lastExpectingField(messages: ChatMessage[]): string | null {
    const last = lastAssistantMessage(messages);
    const ex = last?.meta?.expecting_field;
    return typeof ex === 'string' && ex !== '' ? ex : null;
}

function isReadyForSubmit(messages: ChatMessage[]): boolean {
    const last = lastAssistantMessage(messages);
    return last?.meta?.phase === 'ready_for_submit';
}

function findLastActiveAssistantIndex(messages: DisplayMessage[]): number {
    for (let i = messages.length - 1; i >= 0; i--) {
        const m = messages[i];
        if (m?.role !== 'assistant') {
            continue;
        }
        if (m.meta?.phase === 'ready_for_submit') {
            continue;
        }

        return i;
    }

    return -1;
}

function formatBubbleTime(meta: Record<string, unknown> | undefined): string | null {
    const at = meta?.at;
    if (typeof at !== 'string') {
        return null;
    }
    try {
        return new Intl.DateTimeFormat('pt-PT', { hour: '2-digit', minute: '2-digit' }).format(new Date(at));
    } catch {
        return null;
    }
}

function metaWorkflowNodeRunId(meta: Record<string, unknown> | undefined): number | undefined {
    if (!meta) {
        return undefined;
    }
    const v = meta.workflow_node_run_id;
    if (typeof v === 'number' && Number.isFinite(v)) {
        return v;
    }
    if (typeof v === 'string' && /^\d+$/.test(v)) {
        return parseInt(v, 10);
    }

    return undefined;
}

/** Campo da etapa atual ou metadados gravados na mensagem (etapas anteriores no histórico cumulativo). */
function formFieldForUserMessage(
    userFieldKey: string,
    meta: Record<string, unknown> | undefined,
    stepFields: FormField[],
): FormField | null {
    const fromStep = stepFields.find((f) => f.key === userFieldKey);
    if (fromStep) {
        return fromStep;
    }
    const t = meta?.field_type;
    const label = meta?.field_label;
    if (typeof t !== 'string' || t === '') {
        return null;
    }
    const base: FormField = {
        key: userFieldKey,
        type: t,
        label: typeof label === 'string' && label !== '' ? label : userFieldKey,
    };
    const opt = meta?.field_options;
    if (typeof opt === 'string') {
        base.options = opt;
    }
    const ch = meta?.field_choices;
    if (Array.isArray(ch)) {
        base.choices = normalizeChoices(ch);
    }

    return base;
}

/** Select/cartões só são editáveis se houver opções (vindas da etapa ou de `field_*` na meta). */
function userMessageFieldHasEditableSchema(field: FormField): boolean {
    if (field.type === 'select') {
        return parseSelectOptions(field.options).length > 0;
    }
    if (field.type === 'choice_cards') {
        return normalizeChoices(field.choices).length > 0;
    }

    return true;
}

type ChatEditContext = {
    historyIndex: number;
    field: FormField;
    fieldKey: string;
    nodeRunId?: number;
};

function findLastTransitionIndex(messages: DisplayMessage[]): number {
    for (let i = messages.length - 1; i >= 0; i--) {
        if (messages[i]?.role === 'system') {
            return i;
        }
    }

    return -1;
}

/** Mensagens só da etapa em curso (último bloco após o separador `system`), para API e `ready`. */
function sliceCurrentStepChatMessages(full: DisplayMessage[]): ChatMessage[] {
    const lastSys = findLastTransitionIndex(full);
    const start = lastSys >= 0 ? lastSys + 1 : 0;
    const out: ChatMessage[] = [];
    for (let i = start; i < full.length; i++) {
        const m = full[i];
        if (m.role === 'user' || m.role === 'assistant') {
            out.push(m);
        }
    }

    return out;
}

export function ChatbotRenderer({
    token,
    step,
    run_id,
    prefill,
    initialMessages,
    aiExtractAvailable,
    user,
    workflowName,
    onAdvance,
    onComplete,
    onDraftUpdate,
    chatAdvanceSeq,
    interactionMode,
    onInteractionModeChange,
}: Props) {
    const getInitials = useInitials();
    const userAvatarSrc = useMemo(() => buildFakeUserAvatar(user), [user]);

    // Cumulative conversation across steps. Each step's messages are appended
    // as the workflow advances, with a transition marker in between.
    const [history, setHistory] = useState<DisplayMessage[]>(() => initialMessages);
    const [currentMessages, setCurrentMessages] = useState<ChatMessage[]>(() =>
        sliceCurrentStepChatMessages(initialMessages),
    );
    const [ready, setReady] = useState(() =>
        isReadyForSubmit(sliceCurrentStepChatMessages(initialMessages)),
    );
    const [input, setInput] = useState('');
    const [chatError, setChatError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [advancing, setAdvancing] = useState(false);
    const [extractOpen, setExtractOpen] = useState(false);
    const [extractText, setExtractText] = useState('');
    const [extractBusy, setExtractBusy] = useState(false);
    // Estado da edição inline de respostas do utilizador (qualquer etapa no histórico cumulativo).
    const [editContext, setEditContext] = useState<ChatEditContext | null>(null);
    const [editInput, setEditInput] = useState('');
    const [editSaving, setEditSaving] = useState(false);

    const form = useForm(mergeInitialData(step.fields, prefill));
    const scrollRef = useRef<HTMLDivElement>(null);
    const scrollContentRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);
    const editInputRef = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);
    const prevTokenRef = useRef<string>(token);
    const submitInFlight = useRef(false);
    const autoSubmitLatchRef = useRef(false);
    const initialMessagesRef = useRef(initialMessages);
    initialMessagesRef.current = initialMessages;

    const appliedChatAdvanceSeqRef = useRef<number>(-1);

    // Hidratar histórico: (1) avanço pelo chat (chatAdvanceSeq sobe) + novo token → anexa
    // segmento; (2) mudança de token sem novo seq (Inertia / «Voltar») → substitui pelo servidor.
    useEffect(() => {
        const segmentOrCumulative = initialMessagesRef.current;
        const tokenChanged = prevTokenRef.current !== token;
        const shouldAppendFromChatSubmit =
            chatAdvanceSeq > appliedChatAdvanceSeqRef.current && tokenChanged;

        if (shouldAppendFromChatSubmit) {
            appliedChatAdvanceSeqRef.current = chatAdvanceSeq;
            prevTokenRef.current = token;
            setCurrentMessages(segmentOrCumulative);
            setHistory((prev) => [
                ...prev,
                {
                    role: 'system',
                    content: step.title,
                    meta: { at: new Date().toISOString() },
                } satisfies TransitionMessage,
                ...segmentOrCumulative,
            ]);
            setReady(isReadyForSubmit(segmentOrCumulative));
        } else if (tokenChanged || appliedChatAdvanceSeqRef.current < 0) {
            appliedChatAdvanceSeqRef.current = chatAdvanceSeq;
            prevTokenRef.current = token;
            const full = segmentOrCumulative as DisplayMessage[];
            setHistory(full);
            const currentSeg = sliceCurrentStepChatMessages(full);
            setCurrentMessages(currentSeg);
            setReady(isReadyForSubmit(currentSeg));
        }

        setInput('');
        setEditContext(null);
        setEditInput('');
        form.setData(mergeInitialData(step.fields, prefill));
        // eslint-disable-next-line react-hooks/exhaustive-deps -- `initialMessages` via ref; evitar re-hidratar a cada render
    }, [token, chatAdvanceSeq]);

    // Mantém o foco sempre na última mensagem. Usamos ResizeObserver no
    // conteúdo para acompanhar qualquer crescimento (mensagens novas, widget
    // inline a expandir, spinner, erro, etc.) sem depender de listas de deps.
    useEffect(() => {
        const scroller = scrollRef.current;
        const content = scrollContentRef.current;
        if (!scroller || !content) {
            return;
        }

        const scrollToBottom = (behavior: ScrollBehavior) => {
            scroller.scrollTo({ top: scroller.scrollHeight, behavior });
        };

        scrollToBottom('auto');

        const observer = new ResizeObserver(() => {
            scrollToBottom('smooth');
        });
        observer.observe(content);

        return () => {
            observer.disconnect();
        };
    }, [token]);

    // Garante scroll imediato em transições lógicas (envio, avanço de etapa,
    // erro) mesmo antes do ResizeObserver disparar.
    useEffect(() => {
        const el = scrollRef.current;
        if (!el) {
            return;
        }
        el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
    }, [history, sending, advancing, ready, chatError]);

    useEffect(() => {
        inputRef.current?.focus();
    }, [history.length]);

    const expectingKey = useMemo(() => lastExpectingField(currentMessages), [currentMessages]);
    const expectingField = useMemo(
        () => step.fields.find((f) => f.key === expectingKey) ?? null,
        [expectingKey, step.fields],
    );
    const activeIdx = useMemo(() => findLastActiveAssistantIndex(history), [history]);

    const replaceCurrentBlock = useCallback((nextBlock: ChatMessage[]) => {
        setCurrentMessages(nextBlock);
        setHistory((prev) => {
            const lastTransitionIdx = findLastTransitionIndex(prev);
            const kept = lastTransitionIdx >= 0 ? prev.slice(0, lastTransitionIdx + 1) : [];

            return [...kept, ...nextBlock];
        });
    }, []);

    const sendContent = useCallback(
        async (content: unknown) => {
            setChatError(null);
            setSending(true);
            const res = await postJson<{
                messages: ChatMessage[];
                ready_for_submit: boolean;
                draft_values: Record<string, unknown>;
            }>(chatRoute.url(token), { content });
            setSending(false);
            if (!res.ok) {
                const err = res.data as { errors?: Record<string, string[]> };
                const first =
                    err.errors?.chat?.[0] ??
                    Object.values(err.errors ?? {})[0]?.[0] ??
                    'Não foi possível enviar a mensagem.';
                setChatError(first);

                return;
            }
            replaceCurrentBlock(res.data.messages);
            setReady(res.data.ready_for_submit);
            const draft = res.data.draft_values ?? {};
            for (const [k, v] of Object.entries(draft)) {
                form.setData(k, v as never);
            }
            onDraftUpdate?.({ messages: res.data.messages, draftValues: draft });
        },
        [form, onDraftUpdate, replaceCurrentBlock, token],
    );

    const onSend = useCallback(async () => {
        if (!expectingField || sending) {
            return;
        }
        if (expectingField.type === 'boolean') {
            return;
        }
        if (input.trim() === '' && expectingField.type !== 'textarea') {
            return;
        }
        await sendContent(input);
        setInput('');
    }, [expectingField, input, sendContent, sending]);

    const onBooleanSend = useCallback(
        async (v: boolean) => {
            if (sending) return;
            await sendContent(v);
        },
        [sendContent, sending],
    );

    const runExtract = useCallback(async () => {
        if (!extractText.trim()) {
            return;
        }
        setExtractBusy(true);
        setChatError(null);
        const res = await postJson<{ values: Record<string, unknown> }>(aiExtractRoute.url(token), {
            free_text: extractText,
        });
        setExtractBusy(false);
        if (!res.ok) {
            const body = res.data as { message?: string };
            setChatError(body.message ?? 'Extração falhou.');

            return;
        }
        for (const [k, v] of Object.entries(res.data.values ?? {})) {
            form.setData(k, v as never);
        }
        setExtractOpen(false);
        setExtractText('');
    }, [extractText, form, token]);

    const submitStep = useCallback(async () => {
        if (submitInFlight.current) {
            return;
        }
        submitInFlight.current = true;
        setAdvancing(true);
        setChatError(null);
        try {
            const res = await postJson<
                | {
                      done: false;
                      completed_step: { title: string; submit_label: string };
                      next: ChatAdvancePayload;
                  }
                | {
                      done: true;
                      completed_step: { title: string; submit_label: string };
                      redirect_url: string;
                      message?: string;
                  }
            >(submitChatRoute.url(token), form.data as Record<string, unknown>);

            if (!res.ok) {
                const err = res.data as { errors?: Record<string, string[]>; message?: string };
                const first =
                    Object.values(err.errors ?? {})[0]?.[0] ??
                    err.message ??
                    'Não foi possível submeter esta etapa.';
                setChatError(first);
                autoSubmitLatchRef.current = false;

                return;
            }

            if (res.data.done) {
                const completed = res.data;
                setHistory((prev) => [
                    ...prev,
                    {
                        role: 'system',
                        content: completed.message ?? 'Fluxo concluído.',
                        meta: { at: new Date().toISOString() },
                    } satisfies TransitionMessage,
                ]);
                onComplete(completed.redirect_url);

                return;
            }

            onAdvance(res.data.next);
        } finally {
            setAdvancing(false);
            submitInFlight.current = false;
        }
    }, [form, onAdvance, onComplete, token]);

    // Quando a etapa fica completa na conversa, submete sem pedir confirmação ao utilizador.
    // Se o utilizador estiver a editar uma resposta, suspendemos a auto-submissão até
    // fechar o editor, para evitar avanço involuntário durante a revisão.
    useEffect(() => {
        if (!ready) {
            autoSubmitLatchRef.current = false;

            return;
        }
        if (sending || chatError || editContext) {
            return;
        }
        if (autoSubmitLatchRef.current) {
            return;
        }
        autoSubmitLatchRef.current = true;
        void submitStep();
    }, [chatError, editContext, ready, sending, submitStep, token]);

    const startEdit = useCallback(
        (historyIndex: number, field: FormField, fieldKey: string, currentContent: string, nodeRunId?: number) => {
            setChatError(null);
            autoSubmitLatchRef.current = false;
            setEditContext({ historyIndex, field, fieldKey, nodeRunId });
            setEditInput(currentContent);
            // Foco diferido para garantir que o input já foi montado.
            setTimeout(() => editInputRef.current?.focus(), 0);
        },
        [],
    );

    const cancelEdit = useCallback(() => {
        setEditContext(null);
        setEditInput('');
    }, []);

    const saveEdit = useCallback(async () => {
        if (!editContext || editSaving) {
            return;
        }
        const { field, fieldKey } = editContext;
        if (field.type === 'boolean') {
            return;
        }
        if (editInput.trim() === '' && field.type !== 'textarea') {
            return;
        }
        setEditSaving(true);
        setChatError(null);
        const body: Record<string, unknown> = {
            field_key: fieldKey,
            content: editInput,
        };
        if (editContext.nodeRunId !== undefined) {
            body.workflow_node_run_id = editContext.nodeRunId;
        }
        const res = await postJson<{
            messages: ChatMessage[];
            cumulative_messages?: ChatMessage[];
            ready_for_submit: boolean;
            draft_values: Record<string, unknown>;
        }>(editChatRoute.url(token), body);
        setEditSaving(false);
        if (!res.ok) {
            const err = res.data as { errors?: Record<string, string[]>; message?: string };
            const first =
                err.errors?.content?.[0] ??
                Object.values(err.errors ?? {})[0]?.[0] ??
                err.message ??
                'Não foi possível atualizar a resposta.';
            setChatError(first);

            return;
        }
        const cum = res.data.cumulative_messages;
        if (cum !== undefined && cum.length > 0) {
            const full = cum as DisplayMessage[];
            setHistory(full);
            const seg = sliceCurrentStepChatMessages(full);
            setCurrentMessages(seg);
            setReady(res.data.ready_for_submit);
            const draft = res.data.draft_values ?? {};
            for (const f of step.fields) {
                if (Object.prototype.hasOwnProperty.call(draft, f.key)) {
                    form.setData(f.key, draft[f.key] as never);
                }
            }
            onDraftUpdate?.({ messages: seg, draftValues: draft });
        } else {
            replaceCurrentBlock(res.data.messages);
            setReady(res.data.ready_for_submit);
            const draft = res.data.draft_values ?? {};
            for (const [k, v] of Object.entries(draft)) {
                form.setData(k, v as never);
            }
            onDraftUpdate?.({ messages: res.data.messages, draftValues: draft });
        }
        setEditContext(null);
        setEditInput('');
    }, [editContext, editInput, editSaving, form, onDraftUpdate, replaceCurrentBlock, step.fields, token]);

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col bg-background">
            <WorkflowFormRendererHeader
                workflowName={workflowName}
                stepTitle={step.title}
                run_id={run_id}
                interactionMode={interactionMode}
                onInteractionModeChange={onInteractionModeChange}
            />

            <div
                ref={scrollRef}
                className="scrollbar-discrete min-h-0 flex-1 overflow-y-auto bg-gradient-to-b from-muted/25 via-background to-muted/20 dark:bg-none dark:bg-background"
            >
                <div
                    ref={scrollContentRef}
                    className="mx-auto max-w-2xl px-4 py-6 lg:px-6"
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                >
                    {history.length === 0 ? (
                        <p className="py-12 text-center text-sm text-muted-foreground">A carregar a conversa…</p>
                    ) : null}

                    {history.map((m, idx) => {
                        if (m.role === 'system') {
                            return (
                                <div
                                    key={`sys-${idx}`}
                                    className="my-6 flex items-center gap-3 px-2 text-[11px] font-medium text-muted-foreground"
                                >
                                    <span className="h-px flex-1 bg-border/70" aria-hidden />
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-muted/40 px-2.5 py-1">
                                        <CheckCircle2 className="size-3 text-emerald-500" aria-hidden />
                                        <span className="max-w-[16rem] truncate">{m.content}</span>
                                    </span>
                                    <span className="h-px flex-1 bg-border/70" aria-hidden />
                                </div>
                            );
                        }

                        const prev = history[idx - 1];
                        const next = history[idx + 1];
                        const isNewGroup = idx === 0 || prev?.role !== m.role;
                        const isLastInGroup = !next || next.role !== m.role;
                        const isAssistant = m.role === 'assistant';
                        if (isAssistant && m.meta?.phase === 'ready_for_submit') {
                            return null;
                        }
                        const isActive = !ready && isAssistant && idx === activeIdx && expectingField !== null;
                        const t = formatBubbleTime(m.meta);

                        // Respostas do utilizador com meta.expecting_field podem ser editadas
                        // (incluindo select/cartões se a meta trouxer field_options / field_choices).
                        const userFieldKey =
                            !isAssistant && typeof m.meta?.expecting_field === 'string'
                                ? (m.meta.expecting_field as string)
                                : null;
                        const fieldFromCurrentStep =
                            userFieldKey !== null ? step.fields.find((f) => f.key === userFieldKey) ?? null : null;
                        const userField =
                            fieldFromCurrentStep ??
                            (userFieldKey !== null
                                ? formFieldForUserMessage(
                                      userFieldKey,
                                      m.meta as Record<string, unknown> | undefined,
                                      step.fields,
                                  )
                                : null);
                        const msgNodeRunId = metaWorkflowNodeRunId(m.meta as Record<string, unknown> | undefined);
                        const canEditUserMsg =
                            !isAssistant
                            && userFieldKey !== null
                            && userField !== null
                            && userField.type !== 'boolean'
                            && userMessageFieldHasEditableSchema(userField)
                            && !advancing
                            && !sending
                            && (editContext === null || editContext.historyIndex === idx);
                        const isEditingThis =
                            !isAssistant && editContext !== null && editContext.historyIndex === idx;

                        return (
                            <div
                                key={`${idx}-${m.role}-${String(m.content).slice(0, 24)}`}
                                className={cn(
                                    'flex gap-2.5',
                                    isAssistant ? 'justify-start' : 'justify-end',
                                    isNewGroup ? 'mt-5 first:mt-0' : 'mt-1.5',
                                )}
                            >
                                <div
                                    className={cn(
                                        'flex w-9 shrink-0 flex-col',
                                        isAssistant ? 'items-start' : 'order-2 items-end',
                                    )}
                                >
                                    {isLastInGroup ? (
                                        <Avatar
                                            className={cn(
                                                'size-8 shadow-sm',
                                                isAssistant
                                                    ? 'border border-emerald-200/80 dark:border-emerald-900/60'
                                                    : 'border border-border/60',
                                            )}
                                        >
                                            {isAssistant ? (
                                                <>
                                                    <AvatarImage
                                                        src={ASSISTANT_AVATAR_SRC}
                                                        alt={`Mascote ${ASSISTANT_NAME}`}
                                                        className="object-cover"
                                                    />
                                                    <AvatarFallback className="bg-emerald-100 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-100">
                                                        SS
                                                    </AvatarFallback>
                                                </>
                                            ) : (
                                                <>
                                                    {userAvatarSrc ? (
                                                        <AvatarImage
                                                            src={userAvatarSrc}
                                                            alt={user?.name ?? 'Utilizador'}
                                                            className="object-cover"
                                                        />
                                                    ) : null}
                                                    <AvatarFallback className="bg-primary/90 text-[11px] font-semibold text-primary-foreground">
                                                        {user?.name
                                                            ? getInitials(user.name)
                                                            : 'EU'}
                                                    </AvatarFallback>
                                                </>
                                            )}
                                        </Avatar>
                                    ) : (
                                        <span className="size-8 shrink-0" />
                                    )}
                                </div>

                                <div
                                    className={cn(
                                        'group/msg min-w-0',
                                        isActive || isEditingThis
                                            ? 'w-full max-w-[min(100%,32rem)]'
                                            : 'max-w-[min(100%,28rem)]',
                                        !isAssistant && 'order-1',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'relative rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed',
                                            isAssistant
                                                ? cn(
                                                      'border border-border/60 bg-card text-card-foreground',
                                                      isNewGroup ? 'rounded-tl-2xl' : 'rounded-tl-md',
                                                  )
                                                : cn(
                                                      'bg-primary text-primary-foreground',
                                                      isNewGroup ? 'rounded-tr-2xl' : 'rounded-tr-lg',
                                                  ),
                                        )}
                                    >
                                        {isEditingThis && editContext ? (
                                            <EditableAnswer
                                                field={editContext.field}
                                                value={editInput}
                                                onChange={setEditInput}
                                                onSave={() => void saveEdit()}
                                                onCancel={cancelEdit}
                                                saving={editSaving}
                                                inputRef={editInputRef}
                                            />
                                        ) : (
                                            <p className="whitespace-pre-wrap break-words">{m.content}</p>
                                        )}

                                        {isActive && expectingField ? (
                                            <InlineWidget
                                                field={expectingField}
                                                input={input}
                                                onInputChange={setInput}
                                                onSendText={onSend}
                                                onBoolean={onBooleanSend}
                                                onSelect={(v) => void sendContent(v)}
                                                onChoice={(v) => void sendContent(v)}
                                                sending={sending}
                                                inputRef={inputRef}
                                            />
                                        ) : null}

                                        {canEditUserMsg && userField && userFieldKey ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    startEdit(idx, userField, userFieldKey, m.content, msgNodeRunId)
                                                }
                                                className={cn(
                                                    'absolute -left-2 top-1/2 -translate-x-full -translate-y-1/2',
                                                    'inline-flex items-center gap-1 rounded-full border border-border/60 bg-background px-2 py-1 text-[10px] font-medium text-muted-foreground shadow-sm',
                                                    'opacity-0 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover/msg:opacity-100',
                                                )}
                                                aria-label="Editar esta resposta"
                                                title="Editar esta resposta"
                                            >
                                                <Pencil className="size-3" aria-hidden />
                                                Editar
                                            </button>
                                        ) : null}
                                    </div>
                                    {t && isLastInGroup ? (
                                        <p
                                            className={cn(
                                                'mt-1 px-1 text-[10px] tabular-nums text-muted-foreground',
                                                isAssistant ? 'text-left' : 'text-right',
                                            )}
                                        >
                                            {t}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        );
                    })}

                    {sending || advancing ? (
                        <div className="mt-4 flex justify-start gap-2.5 pl-11">
                            <div className="flex items-center gap-2 rounded-2xl border border-border/60 bg-card px-3 py-2 text-xs text-muted-foreground">
                                <Spinner className="size-3.5" />
                                {advancing ? 'A avançar para a próxima etapa…' : 'A processar…'}
                            </div>
                        </div>
                    ) : null}

                    {chatError ? (
                        <div
                            className="mt-4 space-y-2 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            role="alert"
                        >
                            <p>{chatError}</p>
                            {ready ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 border-destructive/40 text-destructive hover:bg-destructive/15"
                                    disabled={advancing}
                                    onClick={() => {
                                        autoSubmitLatchRef.current = false;
                                        setChatError(null);
                                    }}
                                >
                                    {advancing ? <Spinner className="size-3.5" /> : null}
                                    Tentar novamente
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>

            {aiExtractAvailable && !ready ? (
                <div className="shrink-0 border-t border-border/60 bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto max-w-2xl px-4 py-2 lg:px-6">
                        <div className="overflow-hidden rounded-xl border border-dashed border-border/80 bg-muted/20">
                            <button
                                type="button"
                                onClick={() => setExtractOpen((v) => !v)}
                                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs text-muted-foreground transition-colors hover:bg-muted/40 hover:text-foreground"
                            >
                                <span className="flex items-center gap-2 font-medium">
                                    <Sparkles className="size-3.5 text-violet-600 dark:text-violet-300" />
                                    Preencher com IA (texto livre)
                                </span>
                                {extractOpen ? (
                                    <ChevronUp className="size-3.5 shrink-0 opacity-60" />
                                ) : (
                                    <ChevronDown className="size-3.5 shrink-0 opacity-60" />
                                )}
                            </button>
                            {extractOpen ? (
                                <div className="space-y-2 border-t border-border/60 px-3 py-2.5">
                                    <textarea
                                        value={extractText}
                                        onChange={(e) => setExtractText(e.target.value)}
                                        placeholder="Cola aqui texto com vários dados (nome, email, …)"
                                        rows={3}
                                        className={cn(
                                            'w-full rounded-lg border border-input bg-background px-3 py-2 text-xs outline-none',
                                            'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        )}
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="h-8 rounded-lg"
                                        disabled={extractBusy || !extractText.trim()}
                                        onClick={() => void runExtract()}
                                    >
                                        {extractBusy && <Spinner />}
                                        Aplicar às respostas
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

type InlineWidgetProps = {
    field: FormField;
    input: string;
    onInputChange: (v: string) => void;
    onSendText: () => void | Promise<void>;
    onBoolean: (v: boolean) => void | Promise<void>;
    onSelect: (v: string) => void;
    onChoice: (v: string) => void;
    sending: boolean;
    inputRef: React.MutableRefObject<HTMLInputElement | HTMLTextAreaElement | null>;
};

function InlineWidget({
    field,
    input,
    onInputChange,
    onSendText,
    onBoolean,
    onSelect,
    onChoice,
    sending,
    inputRef,
}: InlineWidgetProps) {
    if (field.type === 'boolean') {
        // Nenhuma opção deve vir destacada como escolha padrão: ambas neutras,
        // iguais visualmente. A seleção só acontece quando o utilizador clica.
        return (
            <div className="mt-3 flex flex-wrap gap-2 border-t border-border/60 pt-3">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="min-w-[4.5rem] rounded-full border-border/80 bg-background shadow-none"
                    disabled={sending}
                    onClick={() => onBoolean(false)}
                >
                    Não
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="min-w-[4.5rem] rounded-full border-border/80 bg-background shadow-none"
                    disabled={sending}
                    onClick={() => onBoolean(true)}
                >
                    Sim
                </Button>
            </div>
        );
    }

    if (field.type === 'choice_cards') {
        return (
            <div className="mt-3 flex flex-wrap gap-2 border-t border-border/60 pt-3">
                {normalizeChoices(field.choices).map((c) => (
                    <Button
                        key={c.value}
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="h-9 rounded-full border border-border/80 px-4 shadow-none"
                        disabled={sending}
                        onClick={() => onChoice(c.value)}
                    >
                        {c.label}
                    </Button>
                ))}
            </div>
        );
    }

    if (field.type === 'select') {
        return (
            <div className="mt-3 space-y-1.5 border-t border-border/60 pt-3">
                <Label className="text-[11px] text-muted-foreground">{field.label}</Label>
                <select
                    className={cn(
                        'h-10 w-full rounded-xl border border-input bg-background px-3 text-sm shadow-xs outline-none transition-colors',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    )}
                    defaultValue=""
                    onChange={(e) => {
                        if (e.target.value !== '') {
                            onSelect(e.target.value);
                        }
                    }}
                    disabled={sending}
                >
                    <option value="" disabled>
                        Escolhe uma opção…
                    </option>
                    {parseSelectOptions(field.options).map((opt) => (
                        <option key={opt} value={opt}>
                            {opt}
                        </option>
                    ))}
                </select>
            </div>
        );
    }

    const placeholder = field.placeholder ?? 'Escreve a tua resposta…';

    if (field.type === 'textarea') {
        return (
            <div className="mt-3 space-y-1.5 border-t border-border/60 pt-3">
                <div className="relative rounded-xl border border-input bg-background transition-shadow focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                    <textarea
                        ref={(el) => {
                            inputRef.current = el;
                        }}
                        value={input}
                        onChange={(e) => onInputChange(e.target.value)}
                        placeholder={placeholder}
                        rows={2}
                        disabled={sending}
                        className="max-h-40 min-h-[3rem] w-full resize-y rounded-xl border-0 bg-transparent px-3 py-2.5 pr-12 text-sm outline-none placeholder:text-muted-foreground"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                void onSendText();
                            }
                        }}
                    />
                    <Button
                        type="button"
                        size="icon"
                        className="absolute right-1.5 bottom-1.5 size-9 rounded-lg"
                        disabled={sending || input.trim() === ''}
                        onClick={() => void onSendText()}
                        aria-label="Enviar"
                    >
                        {sending ? <Spinner /> : <SendHorizonal className="size-4" aria-hidden />}
                    </Button>
                </div>
                <p className="px-1 text-[10px] text-muted-foreground">
                    Enter para enviar · Shift+Enter para nova linha
                </p>
            </div>
        );
    }

    const inputType = field.type === 'email' ? 'email' : field.type === 'number' ? 'number' : 'text';

    return (
        <div className="mt-3 border-t border-border/60 pt-3">
            <div className="relative rounded-xl border border-input bg-background transition-shadow focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                <Input
                    ref={(el) => {
                        inputRef.current = el;
                    }}
                    value={input}
                    onChange={(e) => onInputChange(e.target.value)}
                    placeholder={placeholder}
                    type={inputType}
                    disabled={sending}
                    className="h-11 rounded-xl border-0 pr-12 shadow-none focus-visible:ring-0"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void onSendText();
                        }
                    }}
                />
                <Button
                    type="button"
                    size="icon"
                    className="absolute top-1/2 right-1.5 size-9 -translate-y-1/2 rounded-lg"
                    disabled={sending || input.trim() === ''}
                    onClick={() => void onSendText()}
                    aria-label="Enviar"
                >
                    {sending ? <Spinner /> : <SendHorizonal className="size-4" aria-hidden />}
                </Button>
            </div>
        </div>
    );
}

type EditableAnswerProps = {
    field: FormField;
    value: string;
    onChange: (v: string) => void;
    onSave: () => void;
    onCancel: () => void;
    saving: boolean;
    inputRef: React.MutableRefObject<HTMLInputElement | HTMLTextAreaElement | null>;
};

/**
 * Editor inline para reabrir uma resposta já dada pelo utilizador na etapa
 * atual. Reutiliza os mesmos controlos de entrada do {@link InlineWidget},
 * mas em modo de atualização (com Guardar/Cancelar).
 */
function EditableAnswer({
    field,
    value,
    onChange,
    onSave,
    onCancel,
    saving,
    inputRef,
}: EditableAnswerProps) {
    const canSave = (() => {
        if (saving) return false;
        if (field.type === 'textarea') return true;
        return value.trim() !== '';
    })();

    if (field.type === 'select') {
        return (
            <div className="flex flex-col gap-2">
                <select
                    className={cn(
                        'h-10 w-full rounded-xl border border-primary-foreground/30 bg-primary-foreground/10 px-3 text-sm text-primary-foreground shadow-xs outline-none transition-colors',
                        'focus-visible:border-primary-foreground/60 focus-visible:ring-[3px] focus-visible:ring-primary-foreground/30',
                    )}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={saving}
                >
                    <option value="" disabled>
                        Escolhe uma opção…
                    </option>
                    {parseSelectOptions(field.options).map((opt) => (
                        <option key={opt} value={opt} className="text-foreground">
                            {opt}
                        </option>
                    ))}
                </select>
                <EditActions onSave={onSave} onCancel={onCancel} saving={saving} canSave={value !== ''} />
            </div>
        );
    }

    if (field.type === 'choice_cards') {
        return (
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-1.5">
                    {normalizeChoices(field.choices).map((c) => {
                        const active = c.value === value;
                        return (
                            <button
                                key={c.value}
                                type="button"
                                onClick={() => onChange(c.value)}
                                disabled={saving}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs transition-colors',
                                    active
                                        ? 'border-primary-foreground bg-primary-foreground text-primary'
                                        : 'border-primary-foreground/40 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/20',
                                )}
                            >
                                {c.label}
                            </button>
                        );
                    })}
                </div>
                <EditActions onSave={onSave} onCancel={onCancel} saving={saving} canSave={value !== ''} />
            </div>
        );
    }

    if (field.type === 'textarea') {
        return (
            <div className="flex flex-col gap-2">
                <textarea
                    ref={(el) => {
                        inputRef.current = el;
                    }}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    rows={2}
                    disabled={saving}
                    className={cn(
                        'max-h-40 min-h-[3rem] w-full resize-y rounded-xl border border-primary-foreground/30 bg-primary-foreground/10 px-3 py-2 text-sm text-primary-foreground outline-none',
                        'placeholder:text-primary-foreground/60 focus-visible:border-primary-foreground/60 focus-visible:ring-[3px] focus-visible:ring-primary-foreground/30',
                    )}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                            e.preventDefault();
                            onSave();
                        }
                        if (e.key === 'Escape') {
                            e.preventDefault();
                            onCancel();
                        }
                    }}
                />
                <EditActions onSave={onSave} onCancel={onCancel} saving={saving} canSave={canSave} />
            </div>
        );
    }

    const inputType = field.type === 'email' ? 'email' : field.type === 'number' ? 'number' : 'text';

    return (
        <div className="flex flex-col gap-2">
            <Input
                ref={(el) => {
                    inputRef.current = el;
                }}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                type={inputType}
                disabled={saving}
                className={cn(
                    'h-10 rounded-xl border-primary-foreground/30 bg-primary-foreground/10 text-primary-foreground shadow-none',
                    'placeholder:text-primary-foreground/60 focus-visible:border-primary-foreground/60 focus-visible:ring-primary-foreground/30',
                )}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        onSave();
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        onCancel();
                    }
                }}
            />
            <EditActions onSave={onSave} onCancel={onCancel} saving={saving} canSave={canSave} />
        </div>
    );
}

function EditActions({
    onSave,
    onCancel,
    saving,
    canSave,
}: {
    onSave: () => void;
    onCancel: () => void;
    saving: boolean;
    canSave: boolean;
}) {
    return (
        <div className="flex items-center justify-end gap-1.5">
            <Button
                type="button"
                size="sm"
                variant="ghost"
                className="h-7 rounded-full px-3 text-xs text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground"
                disabled={saving}
                onClick={onCancel}
            >
                <X className="size-3.5" aria-hidden />
                Cancelar
            </Button>
            <Button
                type="button"
                size="sm"
                className="h-7 rounded-full bg-primary-foreground px-3 text-xs text-primary hover:bg-primary-foreground/90"
                disabled={!canSave}
                onClick={onSave}
            >
                {saving ? <Spinner /> : <CheckCircle2 className="size-3.5" aria-hidden />}
                Guardar
            </Button>
        </div>
    );
}
