import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AppHead } from '@/components/app-head';
import { UsersIcon } from '@/components/icons';
import { ScoreBadge } from '@/components/leads/score-badge';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import { AiSuggested } from '@/components/ticket-badges';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button, buttonClasses } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/components/ui/pagination';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create as importLeads } from '@/routes/lead-imports';
import { edit as editLeadProfile } from '@/routes/lead-profile';
import { index as leadsIndex, show as showLead } from '@/routes/leads';
import type { LeadListItem, Option } from '@/types';

type Filters = {
    score: string | null;
    status: string | null;
    search: string | null;
};

const scoreTabs = [
    { value: null, label: 'All' },
    { value: 'hot', label: 'Hot' },
    { value: 'warm', label: 'Warm' },
    { value: 'cold', label: 'Cold' },
    { value: 'unscored', label: 'Not scored' },
];

export default function LeadsIndex({
    leads,
    filters,
    scoreCounts,
    statuses,
    leadFormUrl,
    hasProfile,
}: {
    leads: Paginated<LeadListItem>;
    filters: Filters;
    scoreCounts: Record<string, number>;
    statuses: Option[];
    leadFormUrl: string;
    hasProfile: boolean;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [copied, setCopied] = useState(false);
    const isFirstRender = useRef(true);
    const hasAiWork = leads.data.some((lead) => lead.aiWorking);
    const { start, stop } = usePoll(
        4000,
        { only: ['leads', 'scoreCounts'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasAiWork) {
            start();
        } else {
            stop();
        }
    }, [hasAiWork, start, stop]);

    function applyFilters(changes: Partial<Filters>) {
        const next = { ...filters, search: search || null, ...changes };

        router.get(
            leadsIndex.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['leads', 'filters', 'scoreCounts'],
            },
        );
    }

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = window.setTimeout(
            () => applyFilters({ search: search || null }),
            350,
        );

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const total = Object.values(scoreCounts).reduce(
        (sum, count) => sum + count,
        0,
    );

    return (
        <>
            <AppHead title="Leads" />
            <PageHeader
                title="Leads"
                description="Each lead is scored against your ideal customer and gets a personal first follow-up, ready for you to edit and send."
                actions={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                void navigator.clipboard
                                    .writeText(leadFormUrl)
                                    .then(() => {
                                        setCopied(true);
                                        window.setTimeout(
                                            () => setCopied(false),
                                            2000,
                                        );
                                    })
                            }
                        >
                            {copied ? 'Link copied' : 'Copy lead form link'}
                        </Button>
                        <Link
                            href={importLeads()}
                            className={buttonClasses('primary')}
                        >
                            Import CSV
                        </Link>
                    </>
                }
            />

            {!hasProfile && (
                <Alert
                    tone="warning"
                    className="mb-4"
                    title="Leads can't be scored yet"
                >
                    Describe your ideal customer in{' '}
                    <Link
                        href={editLeadProfile()}
                        className="font-medium underline"
                    >
                        Settings → Lead scoring
                    </Link>{' '}
                    and new leads will be scored hot, warm or cold
                    automatically.
                </Alert>
            )}

            <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
                {scoreTabs.map((tab) => {
                    const count =
                        tab.value === null
                            ? total
                            : (scoreCounts[tab.value] ?? 0);
                    const isActive = filters.score === tab.value;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => applyFilters({ score: tab.value })}
                            className={cn(
                                '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium',
                                isActive
                                    ? 'border-brand text-brand-800'
                                    : 'border-transparent text-slate-500 hover:text-slate-800',
                            )}
                        >
                            {tab.label}
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-xs tabular-nums',
                                    isActive
                                        ? 'bg-brand-100 text-brand-800'
                                        : 'bg-slate-100 text-slate-500',
                                )}
                            >
                                {count}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                <Input
                    type="search"
                    placeholder="Search name, email or company"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    aria-label="Search leads"
                />
                <Select
                    aria-label="Filter by status"
                    value={filters.status ?? ''}
                    onChange={(event) =>
                        applyFilters({ status: event.target.value || null })
                    }
                >
                    <option value="">Any status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
            </div>

            <Card>
                {leads.data.length === 0 ? (
                    <EmptyState
                        icon={UsersIcon}
                        title={
                            filters.score || filters.status || filters.search
                                ? 'No leads match these filters'
                                : 'No leads yet'
                        }
                        description="Leads come from your public lead form or a CSV import. Try submitting the form to watch a lead get scored."
                        action={
                            <a
                                href={leadFormUrl}
                                target="_blank"
                                rel="noreferrer"
                                className={buttonClasses('primary')}
                            >
                                Open the lead form
                            </a>
                        }
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs font-medium text-slate-500">
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 sm:px-6"
                                        >
                                            Lead
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Score
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Status
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Follow-up
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right sm:px-6"
                                        >
                                            Added
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {leads.data.map((lead) => (
                                        <tr
                                            key={lead.id}
                                            className="cursor-pointer hover:bg-slate-50"
                                            onClick={() =>
                                                router.visit(
                                                    showLead.url(lead.id),
                                                )
                                            }
                                        >
                                            <td className="px-5 py-3 sm:px-6">
                                                <Link
                                                    href={showLead(lead.id)}
                                                    className="font-medium text-slate-900 hover:underline"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {lead.name}
                                                </Link>
                                                <p className="text-xs text-slate-500">
                                                    {[
                                                        lead.jobTitle,
                                                        lead.company,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        lead.email}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3">
                                                {lead.score ? (
                                                    <ScoreBadge
                                                        value={lead.score.value}
                                                        label={lead.score.label}
                                                    />
                                                ) : lead.suggestedScore ? (
                                                    <AiSuggested>
                                                        {
                                                            lead.suggestedScore
                                                                .label
                                                        }
                                                    </AiSuggested>
                                                ) : lead.aiWorking ? (
                                                    <span className="flex items-center gap-1.5 text-xs text-slate-500">
                                                        <Spinner className="size-3 text-brand" />{' '}
                                                        Scoring…
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge>
                                                    {lead.status.label}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-3 text-xs">
                                                {lead.followUpSentAt ? (
                                                    <span className="text-emerald-700">
                                                        Sent{' '}
                                                        <RelativeTime
                                                            iso={
                                                                lead.followUpSentAt
                                                            }
                                                        />
                                                    </span>
                                                ) : lead.hasFollowUpDraft ? (
                                                    <AiSuggested>
                                                        Draft ready
                                                    </AiSuggested>
                                                ) : (
                                                    <span className="text-slate-400">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right text-xs whitespace-nowrap text-slate-500 sm:px-6">
                                                <RelativeTime
                                                    iso={lead.createdAt}
                                                />
                                                <p className="text-slate-400">
                                                    {lead.source}
                                                </p>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={leads} only={['leads']} />
                    </>
                )}
            </Card>
        </>
    );
}
