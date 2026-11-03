import { Link, router } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { ActivityIcon, XIcon } from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import { StatTile } from '@/components/stat-tile';
import type { BadgeTone } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import type { Paginated } from '@/components/ui/pagination';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import {
    formatDuration,
    formatInteger,
    formatPercent,
    formatUsd,
} from '@/lib/format';
import { index as aiActivity } from '@/routes/ai-activity';
import { show as showLead } from '@/routes/leads';
import { show as showTicket } from '@/routes/tickets';
import type { Option, SuggestionValue } from '@/types';

type Metrics = {
    decided: number;
    accepted: number;
    edited: number;
    rejected: number;
    pending: number;
    autoApplied: number;
    acceptanceRate: number | null;
    approvalRate: number | null;
    byFeature: {
        feature: string;
        label: string;
        decided: number;
        accepted: number;
        edited: number;
        rejected: number;
        acceptanceRate: number | null;
    }[];
};

type ActivityItem = {
    id: number;
    feature: string;
    field: string;
    suggestedLabel: string;
    finalLabel: string | null;
    decision: string;
    decisionLabel: string;
    decidedBy: string | null;
    decidedAt: string | null;
    createdAt: string | null;
    subject: { type: 'ticket' | 'lead'; id: number; label: string } | null;
};

type Selected = ActivityItem & {
    suggested: SuggestionValue;
    final: SuggestionValue | null;
    call: {
        provider: string;
        model: string;
        inputTokens: number;
        outputTokens: number;
        costUsd: number;
        durationMs: number;
        input: {
            system?: string;
            messages?: { role: string; content: string }[];
        } | null;
        output: Record<string, unknown> | null;
    } | null;
};

type Filters = {
    days: number;
    feature: string | null;
    decision: string | null;
};

const decisionTones: Record<string, BadgeTone> = {
    pending: 'brand',
    accepted: 'success',
    edited: 'info',
    rejected: 'danger',
    auto_applied: 'neutral',
};

/**
 * Readable text for any suggestion value: follow-ups show subject and body, scores show their reason.
 */
function describe(value: SuggestionValue): string {
    if (value.subject) {
        return `${value.subject}\n\n${String(value.value)}`;
    }

    return value.reason ? `${value.label} — ${value.reason}` : value.label;
}

function Breakdown({ metrics }: { metrics: Metrics }) {
    const total = metrics.decided;
    const segments = [
        {
            label: 'Accepted',
            value: metrics.accepted,
            className: 'bg-emerald-500',
        },
        { label: 'Edited', value: metrics.edited, className: 'bg-sky-500' },
        {
            label: 'Rejected',
            value: metrics.rejected,
            className: 'bg-rose-500',
        },
    ];

    if (total === 0) {
        return null;
    }

    return (
        <div>
            <div
                className="flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                role="img"
                aria-label={segments
                    .map((segment) => `${segment.label}: ${segment.value}`)
                    .join(', ')}
            >
                {segments
                    .filter((segment) => segment.value > 0)
                    .map((segment) => (
                        <div
                            key={segment.label}
                            className={segment.className}
                            style={{
                                width: `${(segment.value / total) * 100}%`,
                            }}
                        />
                    ))}
            </div>
            <ul className="mt-3 flex flex-wrap gap-4 text-xs text-slate-600">
                {segments.map((segment) => (
                    <li
                        key={segment.label}
                        className="flex items-center gap-1.5"
                    >
                        <span
                            className={`size-2 rounded-sm ${segment.className}`}
                        />
                        {segment.label}{' '}
                        <span className="font-medium text-slate-900 tabular-nums">
                            {segment.value}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function DetailDrawer({
    item,
    onClose,
}: {
    item: Selected;
    onClose: () => void;
}) {
    return (
        <div
            className="fixed inset-0 z-40 flex justify-end"
            role="dialog"
            aria-modal="true"
            aria-labelledby="activity-detail-title"
        >
            <button
                type="button"
                aria-label="Close details"
                className="absolute inset-0 bg-slate-900/30"
                onClick={onClose}
            />
            <div className="relative flex h-full w-full max-w-xl flex-col bg-white shadow-xl">
                <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4">
                    <div>
                        <p className="text-xs text-slate-500">{item.feature}</p>
                        <h2
                            id="activity-detail-title"
                            className="text-base font-semibold text-slate-900"
                        >
                            {item.field}
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                    >
                        <span className="sr-only">Close</span>
                        <XIcon />
                    </button>
                </div>
                <div className="flex-1 space-y-6 overflow-y-auto px-6 py-5 text-sm">
                    {item.subject && (
                        <p>
                            <span className="text-slate-500">About </span>
                            <Link
                                href={
                                    item.subject.type === 'ticket'
                                        ? showTicket(item.subject.id)
                                        : showLead(item.subject.id)
                                }
                                className="font-medium text-brand hover:underline"
                            >
                                {item.subject.label}
                            </Link>
                        </p>
                    )}

                    <section>
                        <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            Decision
                        </h3>
                        <Badge tone={decisionTones[item.decision] ?? 'neutral'}>
                            {item.decisionLabel}
                        </Badge>
                        {item.decidedBy && (
                            <span className="ml-2 text-slate-500">
                                by {item.decidedBy}{' '}
                                <RelativeTime iso={item.decidedAt} />
                            </span>
                        )}
                        <dl className="mt-3 space-y-3">
                            <div>
                                <dt className="text-xs text-slate-500">
                                    AI suggested
                                </dt>
                                <dd className="mt-1 rounded-lg bg-slate-50 p-3 whitespace-pre-wrap text-slate-800 ring-1 ring-slate-200">
                                    {describe(item.suggested)}
                                </dd>
                            </div>
                            {item.final && item.decision === 'edited' && (
                                <div>
                                    <dt className="text-xs text-slate-500">
                                        What the person used instead
                                    </dt>
                                    <dd className="mt-1 rounded-lg bg-sky-50 p-3 whitespace-pre-wrap text-slate-800 ring-1 ring-sky-200">
                                        {describe(item.final)}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>

                    {item.call && (
                        <>
                            <section>
                                <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    AI call
                                </h3>
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs tabular-nums">
                                    <dt className="text-slate-500">Model</dt>
                                    <dd className="text-right font-mono text-slate-800">
                                        {item.call.model}
                                    </dd>
                                    <dt className="text-slate-500">
                                        Tokens in / out
                                    </dt>
                                    <dd className="text-right text-slate-800">
                                        {formatInteger(item.call.inputTokens)} /{' '}
                                        {formatInteger(item.call.outputTokens)}
                                    </dd>
                                    <dt className="text-slate-500">
                                        Estimated cost
                                    </dt>
                                    <dd className="text-right text-slate-800">
                                        {formatUsd(item.call.costUsd)}
                                    </dd>
                                    <dt className="text-slate-500">Time</dt>
                                    <dd className="text-right text-slate-800">
                                        {formatDuration(item.call.durationMs)}
                                    </dd>
                                </dl>
                            </section>
                            {item.call.input && (
                                <section>
                                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                        Input sent to the AI
                                    </h3>
                                    {item.call.input.system && (
                                        <details className="mb-2 rounded-lg ring-1 ring-slate-200">
                                            <summary className="cursor-pointer px-3 py-2 text-xs font-medium text-slate-600">
                                                Instructions
                                            </summary>
                                            <pre className="max-h-64 overflow-auto border-t border-slate-100 p-3 text-xs whitespace-pre-wrap text-slate-700">
                                                {item.call.input.system}
                                            </pre>
                                        </details>
                                    )}
                                    {item.call.input.messages?.map(
                                        (message, index) => (
                                            <pre
                                                key={index}
                                                className="max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-xs whitespace-pre-wrap text-slate-700 ring-1 ring-slate-200"
                                            >
                                                {message.content}
                                            </pre>
                                        ),
                                    )}
                                </section>
                            )}
                            {item.call.output && (
                                <section>
                                    <h3 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                        Raw AI output
                                    </h3>
                                    <pre className="max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 text-xs whitespace-pre-wrap text-slate-100">
                                        {JSON.stringify(
                                            item.call.output,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                </section>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function AiActivity({
    metrics,
    suggestions,
    selected,
    filters,
    features,
    decisions,
}: {
    metrics: Metrics;
    suggestions: Paginated<ActivityItem>;
    selected: Selected | null;
    filters: Filters;
    features: Option[];
    decisions: Option[];
}) {
    function visit(
        changes: Partial<Filters> & { selected?: number | null },
        only?: string[],
    ) {
        const next = { ...filters, selected: selected?.id ?? null, ...changes };

        router.get(
            aiActivity.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true, only },
        );
    }

    return (
        <>
            <AppHead title="AI activity" />
            <PageHeader
                title="AI activity"
                description="Every AI decision, what it was based on, and whether a person accepted, edited or rejected it."
            />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <Select
                    aria-label="Date range"
                    value={filters.days}
                    onChange={(event) =>
                        visit({
                            days: Number(event.target.value),
                            selected: null,
                        })
                    }
                    className="w-40"
                >
                    {[7, 30, 90].map((days) => (
                        <option key={days} value={days}>
                            Last {days} days
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label="Feature"
                    value={filters.feature ?? ''}
                    onChange={(event) =>
                        visit({
                            feature: event.target.value || null,
                            selected: null,
                        })
                    }
                    className="w-48"
                >
                    <option value="">All features</option>
                    {features.map((feature) => (
                        <option key={feature.value} value={feature.value}>
                            {feature.label}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label="Decision"
                    value={filters.decision ?? ''}
                    onChange={(event) =>
                        visit({
                            decision: event.target.value || null,
                            selected: null,
                        })
                    }
                    className="w-48"
                >
                    <option value="">All decisions</option>
                    {decisions.map((decision) => (
                        <option key={decision.value} value={decision.value}>
                            {decision.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatTile
                    label="Acceptance rate"
                    value={
                        metrics.acceptanceRate === null
                            ? '—'
                            : formatPercent(metrics.acceptanceRate)
                    }
                    hint="Accepted as suggested"
                />
                <StatTile
                    label="Used with or without edits"
                    value={
                        metrics.approvalRate === null
                            ? '—'
                            : formatPercent(metrics.approvalRate)
                    }
                    hint={`${formatInteger(metrics.decided)} reviewed suggestions`}
                />
                <StatTile
                    label="Awaiting review"
                    value={formatInteger(metrics.pending)}
                    hint="Suggestions nobody has decided on"
                />
                <StatTile
                    label="Auto-applied"
                    value={formatInteger(metrics.autoApplied)}
                    hint="Not counted in the rates"
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <Card className="self-start">
                    <CardHeader
                        title="How people judged the AI"
                        description="Accepted ÷ (accepted + edited + rejected)"
                    />
                    <div className="px-5 py-4 sm:px-6">
                        <Breakdown metrics={metrics} />
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-y border-slate-100 text-left text-xs text-slate-500">
                                <th
                                    scope="col"
                                    className="px-5 py-2 font-medium sm:px-6"
                                >
                                    Feature
                                </th>
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right font-medium"
                                >
                                    Reviewed
                                </th>
                                <th
                                    scope="col"
                                    className="px-5 py-2 text-right font-medium sm:px-6"
                                >
                                    Accepted
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 tabular-nums">
                            {metrics.byFeature.map((row) => (
                                <tr key={row.feature}>
                                    <th
                                        scope="row"
                                        className="px-5 py-2.5 text-left font-medium text-slate-800 sm:px-6"
                                    >
                                        {row.label}
                                    </th>
                                    <td className="px-3 py-2.5 text-right text-slate-600">
                                        {row.decided}
                                    </td>
                                    <td className="px-5 py-2.5 text-right font-medium text-slate-900 sm:px-6">
                                        {row.acceptanceRate === null
                                            ? '—'
                                            : formatPercent(row.acceptanceRate)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>

                <Card>
                    <CardHeader
                        title="Decision log"
                        description="Select a row to see the AI's input and output."
                    />
                    {suggestions.data.length === 0 ? (
                        <EmptyState
                            icon={ActivityIcon}
                            title="No AI decisions in this period"
                            description="As tickets and leads arrive, every AI suggestion and the human decision on it shows up here."
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100">
                                {suggestions.data.map((item) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                visit({ selected: item.id }, [
                                                    'selected',
                                                ])
                                            }
                                            className="flex w-full items-start gap-3 px-5 py-3 text-left hover:bg-slate-50 sm:px-6"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm text-slate-900">
                                                    <span className="font-medium">
                                                        {item.field}
                                                    </span>
                                                    <span className="text-slate-500">
                                                        {' '}
                                                        ·{' '}
                                                        {item.subject?.label ??
                                                            'Deleted record'}
                                                    </span>
                                                </p>
                                                <p className="mt-0.5 truncate text-sm text-slate-600">
                                                    {item.suggestedLabel}
                                                    {item.decision ===
                                                        'edited' &&
                                                        item.finalLabel && (
                                                            <span className="text-slate-400">
                                                                {' '}
                                                                →{' '}
                                                                {
                                                                    item.finalLabel
                                                                }
                                                            </span>
                                                        )}
                                                </p>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <Badge
                                                    tone={
                                                        decisionTones[
                                                            item.decision
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {item.decisionLabel}
                                                </Badge>
                                                <p className="mt-1 text-xs text-slate-400">
                                                    {item.decidedBy
                                                        ? `${item.decidedBy}, `
                                                        : ''}
                                                    <RelativeTime
                                                        iso={
                                                            item.decidedAt ??
                                                            item.createdAt
                                                        }
                                                    />
                                                </p>
                                            </div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <Pagination
                                paginator={suggestions}
                                only={['suggestions']}
                            />
                        </>
                    )}
                </Card>
            </div>

            {selected && (
                <DetailDrawer
                    item={selected}
                    onClose={() => visit({ selected: null }, ['selected'])}
                />
            )}
        </>
    );
}
