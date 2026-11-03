import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { SparklesIcon } from '@/components/icons';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAiStream } from '@/hooks/use-ai-stream';
import { cn } from '@/lib/utils';
import { store as runAiTask } from '@/routes/tickets/ai-tasks';
import { store as sendReply } from '@/routes/tickets/replies';
import { store as regenerateDraft } from '@/routes/tickets/reply-drafts';
import { store as rewriteReply } from '@/routes/tickets/reply-rewrites';
import { store as decide } from '@/routes/ai-suggestions/decision';
import type { Suggestion, TicketDetail } from '@/types';

type RewriteStyle = 'friendlier' | 'shorter' | 'more_formal' | 'translate';

const rewriteButtons: {
    style: Exclude<RewriteStyle, 'translate'>;
    label: string;
}[] = [
    { style: 'friendlier', label: 'Friendlier' },
    { style: 'shorter', label: 'Shorter' },
    { style: 'more_formal', label: 'More formal' },
];

export function ReplyComposer({
    ticket,
    languages,
    demoMode,
}: {
    ticket: TicketDetail;
    languages: { code: string; name: string }[];
    demoMode: boolean;
}) {
    const draft: Suggestion | undefined =
        ticket.suggestions.reply?.decision === 'pending'
            ? ticket.suggestions.reply
            : undefined;
    const [body, setBody] = useState(
        draft ? String(draft.suggested.value) : '',
    );
    const [usingDraftId, setUsingDraftId] = useState<number | null>(
        draft?.id ?? null,
    );
    const [previousBody, setPreviousBody] = useState<string | null>(null);
    const [sending, setSending] = useState<'send' | 'resolve' | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [translateTo, setTranslateTo] = useState(
        ticket.language?.value && ticket.language.value !== 'en'
            ? ticket.language.value
            : 'es',
    );
    const stream = useAiStream();
    const streamPurpose = useRef<'rewrite' | 'draft' | null>(null);

    // When a draft arrives later (polling) and the agent hasn't typed anything, drop it into the editor.
    useEffect(() => {
        if (draft && draft.id !== usingDraftId && body.trim() === '') {
            setBody(String(draft.suggested.value));
            setUsingDraftId(draft.id);
        }
    }, [draft, usingDraftId, body]);

    useEffect(() => {
        if (stream.status === 'done' && stream.text) {
            setBody(stream.text);
        }
    }, [stream.status, stream.text]);

    const isStreaming = stream.status === 'streaming';
    const shownText = isStreaming ? stream.text : body;
    const draftIsWorking =
        ticket.draft.status === 'pending' ||
        ticket.draft.status === 'processing';
    const showingDraft = draft !== undefined && usingDraftId === draft.id;

    function rewrite(style: RewriteStyle) {
        setPreviousBody(body);
        streamPurpose.current = 'rewrite';
        void stream.start(rewriteReply.url(ticket.id), {
            text: body,
            style,
            language: style === 'translate' ? translateTo : null,
        });
    }

    function regenerate() {
        setPreviousBody(body);
        streamPurpose.current = 'draft';
        void stream.start(
            regenerateDraft.url(ticket.id),
            {},
            {
                onDone: (payload) => {
                    const suggestion = payload.suggestion as
                        | Suggestion
                        | undefined;
                    setUsingDraftId(suggestion?.id ?? null);
                    router.reload({ only: ['ticket'] });
                },
            },
        );
    }

    function discardDraft() {
        if (!draft) {
            return;
        }

        router.post(
            decide.url(draft.id),
            { decision: 'reject' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBody('');
                    setUsingDraftId(null);
                },
            },
        );
    }

    function undo() {
        if (previousBody !== null) {
            setBody(previousBody);
            setPreviousBody(null);
        }
    }

    function send(resolve: boolean) {
        router.post(
            sendReply.url(ticket.id),
            { body, resolve },
            {
                preserveScroll: true,
                onStart: () => setSending(resolve ? 'resolve' : 'send'),
                onFinish: () => setSending(null),
                onError: (validationErrors) => setErrors(validationErrors),
                onSuccess: () => {
                    setBody('');
                    setPreviousBody(null);
                    setUsingDraftId(null);
                    setErrors({});
                },
            },
        );
    }

    return (
        <Card>
            <div className="border-b border-slate-100 px-5 py-3 sm:px-6">
                <p className="text-sm font-semibold text-slate-900">
                    Reply to {ticket.requesterName}
                </p>
            </div>

            {showingDraft && draft && !isStreaming && (
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 bg-brand-50/60 px-5 py-2.5 sm:px-6">
                    <p className="flex items-center gap-1.5 text-xs text-brand-800">
                        <SparklesIcon className="size-3.5" />
                        <span className="font-medium">
                            AI draft, review before sending.
                        </span>
                        {draft.suggested.sources &&
                            draft.suggested.sources.length > 0 && (
                                <span className="text-slate-500">
                                    Based on:{' '}
                                    {draft.suggested.sources.join(', ')}
                                </span>
                            )}
                    </p>
                    <Button size="sm" variant="ghost" onClick={discardDraft}>
                        Discard draft
                    </Button>
                </div>
            )}

            <div className="px-5 py-4 sm:px-6">
                {draftIsWorking && !body && !isStreaming ? (
                    <div
                        className="space-y-2 rounded-lg bg-slate-50 p-4"
                        aria-live="polite"
                    >
                        <p className="flex items-center gap-2 text-sm text-slate-500">
                            <SparklesIcon className="size-4 animate-pulse text-brand" />
                            Drafting a reply from your knowledge snippets…
                        </p>
                        <div className="h-3 w-full animate-pulse rounded bg-slate-200" />
                        <div className="h-3 w-5/6 animate-pulse rounded bg-slate-200" />
                        <div className="h-3 w-2/3 animate-pulse rounded bg-slate-200" />
                    </div>
                ) : (
                    <>
                        <label htmlFor="reply-body" className="sr-only">
                            Reply
                        </label>
                        <Textarea
                            id="reply-body"
                            rows={9}
                            value={shownText}
                            readOnly={isStreaming}
                            onChange={(event) => setBody(event.target.value)}
                            placeholder="Write your reply…"
                            className={cn(isStreaming && 'bg-brand-50/40')}
                            aria-invalid={!!errors.body}
                            aria-busy={isStreaming}
                        />
                        <FieldError message={errors.body} />
                    </>
                )}

                {ticket.draft.status === 'failed' && !body && (
                    <Alert
                        tone="error"
                        className="mt-3"
                        title="The AI couldn't draft a reply"
                    >
                        <p>{ticket.draft.error}</p>
                        <Button
                            size="sm"
                            variant="secondary"
                            className="mt-2"
                            onClick={() =>
                                router.post(
                                    runAiTask.url({
                                        ticket: ticket.id,
                                        task: 'draft',
                                    }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Try again
                        </Button>
                    </Alert>
                )}

                {stream.status === 'error' && stream.error && (
                    <Alert tone="error" className="mt-3">
                        {stream.error}
                    </Alert>
                )}

                <div
                    className="mt-3 flex flex-wrap items-center gap-1.5"
                    role="toolbar"
                    aria-label="Rewrite with AI"
                >
                    <span className="mr-1 flex items-center gap-1 text-xs font-medium text-slate-500">
                        <SparklesIcon className="size-3.5 text-brand" />{' '}
                        Rewrite:
                    </span>
                    {rewriteButtons.map((button) => (
                        <Button
                            key={button.style}
                            size="sm"
                            variant="secondary"
                            disabled={!body.trim() || isStreaming}
                            onClick={() => rewrite(button.style)}
                        >
                            {button.label}
                        </Button>
                    ))}
                    <span className="flex items-center gap-1">
                        <Select
                            aria-label="Translate to"
                            value={translateTo}
                            onChange={(event) =>
                                setTranslateTo(event.target.value)
                            }
                            className="h-8 w-32 py-1 text-xs"
                            disabled={isStreaming}
                        >
                            {languages.map((language) => (
                                <option
                                    key={language.code}
                                    value={language.code}
                                >
                                    {language.name}
                                </option>
                            ))}
                        </Select>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={!body.trim() || isStreaming}
                            onClick={() => rewrite('translate')}
                        >
                            Translate
                        </Button>
                    </span>
                    <span className="mx-1 hidden h-5 w-px bg-slate-200 sm:block" />
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={isStreaming || draftIsWorking}
                        onClick={regenerate}
                    >
                        {draft || usingDraftId
                            ? 'Regenerate draft'
                            : 'Draft with AI'}
                    </Button>
                    {isStreaming && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                stream.cancel();
                                undo();
                            }}
                        >
                            Stop
                        </Button>
                    )}
                    {!isStreaming && previousBody !== null && (
                        <Button size="sm" variant="ghost" onClick={undo}>
                            Undo
                        </Button>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-b-xl border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6">
                <p className="text-xs text-slate-500">
                    {demoMode
                        ? 'Demo mode: replies are saved to the conversation but never emailed.'
                        : `Sends an email to ${ticket.requesterEmail}.`}
                </p>
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        disabled={!body.trim() || isStreaming}
                        loading={sending === 'resolve'}
                        onClick={() => send(true)}
                    >
                        Send and resolve
                    </Button>
                    <Button
                        disabled={!body.trim() || isStreaming}
                        loading={sending === 'send'}
                        onClick={() => send(false)}
                    >
                        Send reply
                    </Button>
                </div>
            </div>
        </Card>
    );
}
