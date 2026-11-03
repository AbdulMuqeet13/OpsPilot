import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircleIcon, SparklesIcon } from '@/components/icons';
import { RelativeTime } from '@/components/relative-time';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as decide } from '@/routes/ai-suggestions/decision';
import { store as runAiTask } from '@/routes/leads/ai-tasks';
import { store as markSent } from '@/routes/leads/follow-up';
import type { LeadDetail } from '@/types';

export function FollowUpComposer({ lead }: { lead: LeadDetail }) {
    const draft =
        lead.suggestions.follow_up?.decision === 'pending'
            ? lead.suggestions.follow_up
            : undefined;
    const [subject, setSubject] = useState(draft?.suggested.label ?? '');
    const [body, setBody] = useState(
        draft ? String(draft.suggested.value) : '',
    );
    const [loadedDraftId, setLoadedDraftId] = useState<number | null>(
        draft?.id ?? null,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [copied, setCopied] = useState(false);
    const isWorking =
        lead.followUp.status === 'pending' ||
        lead.followUp.status === 'processing';

    // Load a draft that arrives later (polling or regeneration) unless the user has started typing.
    useEffect(() => {
        if (
            draft &&
            draft.id !== loadedDraftId &&
            (body.trim() === '' || loadedDraftId !== null)
        ) {
            setSubject(draft.suggested.label);
            setBody(String(draft.suggested.value));
            setLoadedDraftId(draft.id);
        }
    }, [draft, loadedDraftId, body]);

    function send() {
        router.post(
            markSent.url(lead.id),
            { subject, body },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onError: (validationErrors) => setErrors(validationErrors),
                onSuccess: () => setErrors({}),
            },
        );
    }

    const mailto = `mailto:${encodeURIComponent(lead.email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

    if (lead.followUp.sentAt) {
        return (
            <Card>
                <CardHeader
                    title={
                        <span className="flex items-center gap-2">
                            <CheckCircleIcon className="size-4 text-emerald-600" />{' '}
                            Follow-up sent
                        </span>
                    }
                    description={
                        <>
                            Marked as sent
                            {lead.followUp.sentBy && (
                                <> by {lead.followUp.sentBy}</>
                            )}{' '}
                            <RelativeTime iso={lead.followUp.sentAt} />.
                        </>
                    }
                />
                <CardBody>
                    <p className="text-sm font-medium text-slate-900">
                        {lead.followUp.sentSubject}
                    </p>
                    <p className="mt-2 text-sm whitespace-pre-wrap text-slate-700">
                        {lead.followUp.sentBody}
                    </p>
                </CardBody>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader
                title="First follow-up"
                description="Send it from your own mailbox, then mark it as sent here."
                action={
                    <Button
                        size="sm"
                        variant="ghost"
                        disabled={isWorking}
                        onClick={() =>
                            router.post(
                                runAiTask.url({
                                    lead: lead.id,
                                    task: 'follow_up',
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {draft ? 'Regenerate draft' : 'Draft with AI'}
                    </Button>
                }
            />

            {draft && !isWorking && (
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 bg-brand-50/60 px-5 py-2.5 sm:px-6">
                    <p className="flex items-center gap-1.5 text-xs font-medium text-brand-800">
                        <SparklesIcon className="size-3.5" /> AI draft,
                        personalise it before sending.
                    </p>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            router.post(
                                decide.url(draft.id),
                                { decision: 'reject' },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setSubject('');
                                        setBody('');
                                        setLoadedDraftId(null);
                                    },
                                },
                            )
                        }
                    >
                        Discard draft
                    </Button>
                </div>
            )}

            <CardBody className="space-y-4">
                {isWorking ? (
                    <div
                        className="animate-pulse space-y-2 rounded-lg bg-slate-50 p-4"
                        aria-live="polite"
                    >
                        <p className="flex items-center gap-2 text-sm text-slate-500">
                            <SparklesIcon className="size-4 text-brand" />{' '}
                            Writing a personal follow-up…
                        </p>
                        <div className="h-3 w-1/2 rounded bg-slate-200" />
                        <div className="h-3 w-full rounded bg-slate-200" />
                        <div className="h-3 w-5/6 rounded bg-slate-200" />
                    </div>
                ) : (
                    <>
                        {lead.followUp.status === 'failed' &&
                            lead.followUp.error && (
                                <Alert tone="error">
                                    {lead.followUp.error}
                                </Alert>
                            )}
                        <div>
                            <Label
                                htmlFor="follow-up-subject"
                                className="mb-1.5"
                            >
                                Subject
                            </Label>
                            <Input
                                id="follow-up-subject"
                                value={subject}
                                onChange={(event) =>
                                    setSubject(event.target.value)
                                }
                                maxLength={200}
                                aria-invalid={!!errors.subject}
                            />
                            <FieldError message={errors.subject} />
                        </div>
                        <div>
                            <Label htmlFor="follow-up-body" className="mb-1.5">
                                Email
                            </Label>
                            <Textarea
                                id="follow-up-body"
                                rows={10}
                                value={body}
                                onChange={(event) =>
                                    setBody(event.target.value)
                                }
                                aria-invalid={!!errors.body}
                            />
                            <FieldError message={errors.body} />
                        </div>
                    </>
                )}
            </CardBody>
            <CardFooter className="flex-wrap justify-between">
                <div className="flex gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={!body.trim()}
                        onClick={() =>
                            void navigator.clipboard
                                .writeText(`${subject}\n\n${body}`)
                                .then(() => {
                                    setCopied(true);
                                    window.setTimeout(
                                        () => setCopied(false),
                                        2000,
                                    );
                                })
                        }
                    >
                        {copied ? 'Copied' : 'Copy'}
                    </Button>
                    <a
                        href={body.trim() ? mailto : undefined}
                        aria-disabled={!body.trim()}
                        className="inline-flex h-8 items-center rounded-lg px-3 text-xs font-medium text-slate-600 hover:bg-slate-100 aria-disabled:pointer-events-none aria-disabled:opacity-50"
                    >
                        Open in email app
                    </a>
                </div>
                <Button
                    disabled={!subject.trim() || !body.trim() || isWorking}
                    loading={sending}
                    onClick={send}
                >
                    Mark as sent
                </Button>
            </CardFooter>
        </Card>
    );
}
