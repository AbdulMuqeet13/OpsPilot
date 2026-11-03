import { router } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircleIcon, SparklesIcon, XIcon } from '@/components/icons';
import { ScoreBadge } from '@/components/leads/score-badge';
import { RelativeTime } from '@/components/relative-time';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { store as decide } from '@/routes/ai-suggestions/decision';
import { store as runAiTask } from '@/routes/leads/ai-tasks';
import type { LeadDetail } from '@/types';

const scores = [
    { value: 'hot', label: 'Hot' },
    { value: 'warm', label: 'Warm' },
    { value: 'cold', label: 'Cold' },
];

export function LeadScorePanel({ lead }: { lead: LeadDetail }) {
    const suggestion = lead.suggestions.lead_score;
    const isPending = suggestion?.decision === 'pending';
    const isWorking =
        lead.scoring.status === 'pending' ||
        lead.scoring.status === 'processing';
    const [editing, setEditing] = useState(false);
    const [editValue, setEditValue] = useState(
        String(suggestion?.suggested.value ?? 'warm'),
    );
    const [processing, setProcessing] = useState<string | null>(null);

    function submit(decision: 'accept' | 'edit' | 'reject', value?: string) {
        if (!suggestion) {
            return;
        }

        router.post(
            decide.url(suggestion.id),
            { decision, value },
            {
                preserveScroll: true,
                onStart: () => setProcessing(decision),
                onFinish: () => setProcessing(null),
                onSuccess: () => setEditing(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader
                title={
                    <span className="flex items-center gap-2">
                        <SparklesIcon className="size-4 text-brand" /> AI lead
                        score
                    </span>
                }
                description="Scored against your ideal customer description."
                action={
                    !isWorking && lead.scoring.status !== 'skipped' ? (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.post(
                                    runAiTask.url({
                                        lead: lead.id,
                                        task: 'scoring',
                                    }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Re-score
                        </Button>
                    ) : null
                }
            />
            <CardBody className="space-y-4">
                {isWorking && (
                    <div className="animate-pulse space-y-3" aria-live="polite">
                        <p className="flex items-center gap-2 text-sm text-slate-500">
                            <SparklesIcon className="size-4 text-brand" />{' '}
                            Comparing this lead with your ideal customer…
                        </p>
                        <div className="h-5 w-16 rounded bg-slate-200" />
                        <div className="h-3 w-full rounded bg-slate-100" />
                        <div className="h-3 w-4/5 rounded bg-slate-100" />
                    </div>
                )}

                {lead.scoring.error && !isWorking && (
                    <Alert
                        tone={
                            lead.scoring.status === 'skipped' ? 'info' : 'error'
                        }
                    >
                        <p>{lead.scoring.error}</p>
                        {lead.scoring.status === 'failed' && (
                            <Button
                                size="sm"
                                variant="secondary"
                                className="mt-2"
                                onClick={() =>
                                    router.post(
                                        runAiTask.url({
                                            lead: lead.id,
                                            task: 'scoring',
                                        }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Try again
                            </Button>
                        )}
                    </Alert>
                )}

                {!isWorking && isPending && suggestion && (
                    <div className="rounded-lg border border-dashed border-brand-200 bg-brand-50/50 p-4">
                        <p className="mb-2 flex items-center gap-1 text-[11px] font-medium text-brand-800">
                            <SparklesIcon className="size-3" /> AI suggests
                        </p>
                        <ScoreBadge
                            value={String(suggestion.suggested.value)}
                            label={suggestion.suggested.label}
                            className="text-sm"
                        />
                        {suggestion.suggested.reason && (
                            <p className="mt-2 text-sm text-slate-700">
                                {suggestion.suggested.reason}
                            </p>
                        )}
                        {suggestion.suggested.signals &&
                            suggestion.suggested.signals.length > 0 && (
                                <ul className="mt-3 flex flex-wrap gap-1.5">
                                    {suggestion.suggested.signals.map(
                                        (signal) => (
                                            <li
                                                key={signal}
                                                className="rounded-full bg-white px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200"
                                            >
                                                {signal}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}

                        {editing ? (
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <label htmlFor="score-edit" className="sr-only">
                                    Score
                                </label>
                                <Select
                                    id="score-edit"
                                    value={editValue}
                                    onChange={(event) =>
                                        setEditValue(event.target.value)
                                    }
                                    className="w-32"
                                >
                                    {scores.map((score) => (
                                        <option
                                            key={score.value}
                                            value={score.value}
                                        >
                                            {score.label}
                                        </option>
                                    ))}
                                </Select>
                                <Button
                                    size="sm"
                                    loading={processing === 'edit'}
                                    onClick={() => submit('edit', editValue)}
                                >
                                    Save
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setEditing(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        ) : (
                            <div className="mt-4 flex gap-2">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    loading={processing === 'accept'}
                                    onClick={() => submit('accept')}
                                >
                                    <CheckCircleIcon className="size-3.5 text-emerald-600" />{' '}
                                    Accept
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setEditing(true)}
                                >
                                    Change score
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    loading={processing === 'reject'}
                                    onClick={() => submit('reject')}
                                    aria-label="Reject suggested score"
                                >
                                    <XIcon className="size-3.5" />
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {!isWorking &&
                    !isPending &&
                    (lead.score ? (
                        <div>
                            <ScoreBadge
                                value={lead.score.value}
                                label={lead.score.label}
                                className="text-sm"
                            />
                            {lead.scoreReason && (
                                <p className="mt-2 text-sm text-slate-700">
                                    {lead.scoreReason}
                                </p>
                            )}
                            {suggestion &&
                                suggestion.decision !== 'superseded' && (
                                    <p className="mt-2 text-[11px] text-slate-400">
                                        AI score{' '}
                                        {suggestion.decisionLabel.toLowerCase()}
                                        {suggestion.decidedBy && (
                                            <> by {suggestion.decidedBy}</>
                                        )}{' '}
                                        <RelativeTime
                                            iso={suggestion.decidedAt}
                                        />
                                    </p>
                                )}
                        </div>
                    ) : (
                        !lead.scoring.error && (
                            <p className="text-sm text-slate-400">
                                Not scored.
                            </p>
                        )
                    ))}
            </CardBody>
        </Card>
    );
}
