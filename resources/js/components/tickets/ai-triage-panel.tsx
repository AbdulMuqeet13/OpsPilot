import { router } from '@inertiajs/react';
import { useState } from 'react';
import { RelativeTime } from '@/components/relative-time';
import { CheckCircleIcon, SparklesIcon, XIcon } from '@/components/icons';
import {
    CategoryBadge,
    PriorityBadge,
    SentimentLabel,
} from '@/components/ticket-badges';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store as decide } from '@/routes/ai-suggestions/decision';
import { store as runAiTask } from '@/routes/tickets/ai-tasks';
import { store as acceptAll } from '@/routes/tickets/triage-acceptance';
import type {
    CategoryBadgeData,
    Option,
    Suggestion,
    SuggestionValue,
    TicketDetail,
} from '@/types';

type Choices = {
    categories: CategoryBadgeData[];
    priorities: Option[];
    languages: { code: string; name: string }[];
};

const triageFields = [
    'summary',
    'category',
    'priority',
    'sentiment',
    'language',
] as const;
type TriageField = (typeof triageFields)[number];

const fieldLabels: Record<TriageField, string> = {
    summary: 'Summary',
    category: 'Category',
    priority: 'Priority',
    sentiment: 'Sentiment',
    language: 'Language',
};

const sentimentOptions: Option[] = [
    { value: 'positive', label: 'Positive' },
    { value: 'neutral', label: 'Neutral' },
    { value: 'negative', label: 'Negative' },
];

function ValueDisplay({
    field,
    value,
    categories,
}: {
    field: TriageField;
    value: SuggestionValue;
    categories: CategoryBadgeData[];
}) {
    switch (field) {
        case 'category': {
            const category = categories.find(
                (item) => item.id === Number(value.value),
            );

            return category ? (
                <CategoryBadge category={category} />
            ) : (
                <span className="text-sm text-slate-700">{value.label}</span>
            );
        }
        case 'priority':
            return (
                <PriorityBadge
                    value={String(value.value)}
                    label={value.label}
                />
            );
        case 'sentiment':
            return (
                <span className="inline-flex items-center gap-2">
                    <SentimentLabel
                        value={String(value.value)}
                        label={value.label}
                    />
                    {typeof value.score === 'number' && (
                        <span className="text-xs text-slate-400 tabular-nums">
                            {value.score > 0 ? '+' : ''}
                            {value.score.toFixed(1)}
                        </span>
                    )}
                </span>
            );
        default:
            return (
                <span className="text-sm text-slate-700">{value.label}</span>
            );
    }
}

function EditControl({
    field,
    initial,
    choices,
    onCancel,
    onSave,
    saving,
}: {
    field: TriageField;
    initial: SuggestionValue;
    choices: Choices;
    onCancel: () => void;
    onSave: (value: string) => void;
    saving: boolean;
}) {
    const [value, setValue] = useState(String(initial.value));
    const id = `edit-${field}`;

    const control = {
        summary: (
            <Textarea
                id={id}
                rows={3}
                maxLength={200}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            />
        ),
        category: (
            <Select
                id={id}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            >
                {choices.categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </Select>
        ),
        priority: (
            <Select
                id={id}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            >
                {choices.priorities.map((priority) => (
                    <option key={priority.value} value={priority.value}>
                        {priority.label}
                    </option>
                ))}
            </Select>
        ),
        sentiment: (
            <Select
                id={id}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            >
                {sentimentOptions.map((sentiment) => (
                    <option key={sentiment.value} value={sentiment.value}>
                        {sentiment.label}
                    </option>
                ))}
            </Select>
        ),
        language: choices.languages.some(
            (language) => language.code === value,
        ) ? (
            <Select
                id={id}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            >
                {choices.languages.map((language) => (
                    <option key={language.code} value={language.code}>
                        {language.name}
                    </option>
                ))}
            </Select>
        ) : (
            <Input
                id={id}
                value={value}
                onChange={(event) => setValue(event.target.value)}
            />
        ),
    }[field];

    return (
        <div className="mt-2 space-y-2">
            <label htmlFor={id} className="sr-only">
                {fieldLabels[field]}
            </label>
            {control}
            <div className="flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={onCancel}>
                    Cancel
                </Button>
                <Button
                    size="sm"
                    loading={saving}
                    onClick={() => onSave(value)}
                >
                    Save
                </Button>
            </div>
        </div>
    );
}

function SuggestionRow({
    field,
    suggestion,
    current,
    choices,
}: {
    field: TriageField;
    suggestion: Suggestion | undefined;
    current: SuggestionValue | null;
    choices: Choices;
}) {
    const [editing, setEditing] = useState(false);
    const [processing, setProcessing] = useState<string | null>(null);
    const isPending = suggestion?.decision === 'pending';

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
        <div className="py-3">
            <div className="flex items-start justify-between gap-3">
                <p className="pt-0.5 text-xs font-medium tracking-wide text-slate-500 uppercase">
                    {fieldLabels[field]}
                </p>
                {isPending && !editing && (
                    <div className="flex shrink-0 gap-1">
                        <Button
                            size="sm"
                            variant="secondary"
                            loading={processing === 'accept'}
                            onClick={() => submit('accept')}
                            aria-label={`Accept suggested ${fieldLabels[field].toLowerCase()}`}
                        >
                            <CheckCircleIcon className="size-3.5 text-emerald-600" />
                            Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(true)}
                        >
                            Edit
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            loading={processing === 'reject'}
                            onClick={() => submit('reject')}
                            aria-label={`Reject suggested ${fieldLabels[field].toLowerCase()}`}
                        >
                            <XIcon className="size-3.5" />
                        </Button>
                    </div>
                )}
            </div>

            <div className="mt-1.5">
                {isPending && suggestion ? (
                    editing ? (
                        <EditControl
                            field={field}
                            initial={suggestion.suggested}
                            choices={choices}
                            saving={processing === 'edit'}
                            onCancel={() => setEditing(false)}
                            onSave={(value) => submit('edit', value)}
                        />
                    ) : (
                        <div className="rounded-lg border border-dashed border-brand-200 bg-brand-50/50 px-3 py-2">
                            <p className="mb-1 flex items-center gap-1 text-[11px] font-medium text-brand-800">
                                <SparklesIcon className="size-3" /> AI suggests
                                {current && (
                                    <span className="font-normal text-slate-500">
                                        {' '}
                                        · currently {current.label}
                                    </span>
                                )}
                            </p>
                            <ValueDisplay
                                field={field}
                                value={suggestion.suggested}
                                categories={choices.categories}
                            />
                        </div>
                    )
                ) : current ? (
                    <ValueDisplay
                        field={field}
                        value={current}
                        categories={choices.categories}
                    />
                ) : (
                    <p className="text-sm text-slate-400">Not set</p>
                )}
            </div>

            {suggestion &&
                !isPending &&
                suggestion.decision !== 'superseded' && (
                    <p className="mt-1.5 text-[11px] text-slate-400">
                        AI suggestion {suggestion.decisionLabel.toLowerCase()}
                        {suggestion.decidedBy && (
                            <> by {suggestion.decidedBy}</>
                        )}
                        {suggestion.decidedAt && (
                            <>
                                {' '}
                                <RelativeTime iso={suggestion.decidedAt} />
                            </>
                        )}
                        {suggestion.decision === 'rejected' && (
                            <> (suggested: {suggestion.suggested.label})</>
                        )}
                    </p>
                )}
        </div>
    );
}

function currentValue(
    ticket: TicketDetail,
    field: TriageField,
    choices: Choices,
): SuggestionValue | null {
    switch (field) {
        case 'summary':
            return ticket.summary
                ? { value: ticket.summary, label: ticket.summary }
                : null;
        case 'category':
            return ticket.category
                ? { value: ticket.category.id, label: ticket.category.name }
                : null;
        case 'priority':
            return ticket.priority
                ? {
                      value: ticket.priority,
                      label:
                          choices.priorities.find(
                              (option) => option.value === ticket.priority,
                          )?.label ?? ticket.priority,
                  }
                : null;
        case 'sentiment':
            return ticket.sentiment;
        case 'language':
            return ticket.language;
    }
}

export function AiTriagePanel({
    ticket,
    choices,
    canManageSettings,
}: {
    ticket: TicketDetail;
    choices: Choices;
    canManageSettings: boolean;
}) {
    const pendingCount = triageFields.filter(
        (field) => ticket.suggestions[field]?.decision === 'pending',
    ).length;
    const isWorking =
        ticket.triage.status === 'pending' ||
        ticket.triage.status === 'processing';

    return (
        <Card>
            <CardHeader
                title={
                    <span className="flex items-center gap-2">
                        <SparklesIcon className="size-4 text-brand" />
                        AI triage
                    </span>
                }
                description={
                    pendingCount > 0
                        ? `${pendingCount} suggestion${pendingCount === 1 ? '' : 's'} waiting for your review.`
                        : 'Reviewed. You can still change anything by hand.'
                }
                action={
                    pendingCount > 1 ? (
                        <Button
                            size="sm"
                            onClick={() =>
                                router.post(
                                    acceptAll.url(ticket.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Accept all
                        </Button>
                    ) : null
                }
            />
            <CardBody className="py-2">
                {ticket.triage.status === 'failed' && (
                    <Alert
                        tone="error"
                        className="my-3"
                        title="Triage didn't finish"
                    >
                        <p>{ticket.triage.error}</p>
                        <Button
                            size="sm"
                            variant="secondary"
                            className="mt-2"
                            onClick={() =>
                                router.post(
                                    runAiTask.url({
                                        ticket: ticket.id,
                                        task: 'triage',
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
                {ticket.triage.status === 'skipped' && (
                    <Alert tone="info" className="my-3">
                        Ticket triage is switched off
                        {canManageSettings ? ' in Settings → AI features' : ''}.
                    </Alert>
                )}
                {isWorking ? (
                    <div className="space-y-4 py-3" aria-live="polite">
                        <p className="flex items-center gap-2 text-sm text-slate-500">
                            <SparklesIcon className="size-4 animate-pulse text-brand" />
                            Reading the ticket and preparing suggestions…
                        </p>
                        {[0, 1, 2, 3].map((row) => (
                            <div key={row} className="animate-pulse space-y-2">
                                <div className="h-2.5 w-16 rounded bg-slate-200" />
                                <div className="h-4 w-40 rounded bg-slate-100" />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {triageFields
                            .filter(
                                (field) =>
                                    field !== 'category' ||
                                    choices.categories.length > 0,
                            )
                            .map((field) => (
                                <SuggestionRow
                                    key={field}
                                    field={field}
                                    suggestion={ticket.suggestions[field]}
                                    current={currentValue(
                                        ticket,
                                        field,
                                        choices,
                                    )}
                                    choices={choices}
                                />
                            ))}
                    </div>
                )}
            </CardBody>
        </Card>
    );
}
