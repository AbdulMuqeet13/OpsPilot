import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AppHead } from '@/components/app-head';
import { InboxIcon, SparklesIcon } from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import {
    AiSuggested,
    CategoryBadge,
    PriorityBadge,
    SentimentLabel,
} from '@/components/ticket-badges';
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
import { create as inboundEmail } from '@/routes/inbound-email';
import { index as ticketsIndex, show as showTicket } from '@/routes/tickets';
import type { CategoryBadgeData, Option, TicketListItem } from '@/types';

type Filters = {
    status: string;
    priority: string | null;
    category: number | null;
    search: string | null;
    sort: 'recent' | 'priority';
};

const statusTabs = [
    { value: 'open', label: 'Open' },
    { value: 'pending', label: 'Waiting on customer' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'all', label: 'All' },
];

function TicketRow({ ticket }: { ticket: TicketListItem }) {
    const summary = ticket.summary ?? ticket.suggested.summary?.label ?? null;
    const category = ticket.category ?? null;
    const suggestedCategory = !category ? ticket.suggested.category : null;
    const priority = ticket.priority ?? null;
    const suggestedPriority = !priority ? ticket.suggested.priority : null;
    const sentiment =
        ticket.sentiment ??
        (ticket.suggested.sentiment
            ? {
                  value: String(ticket.suggested.sentiment.value),
                  label: ticket.suggested.sentiment.label,
              }
            : null);

    return (
        <li>
            <Link
                href={showTicket(ticket.id)}
                prefetch
                className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-slate-50 sm:flex-row sm:items-start sm:gap-6 sm:px-6"
            >
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-medium text-slate-400 tabular-nums">
                            {ticket.reference}
                        </span>
                        <p className="truncate text-sm font-semibold text-slate-900">
                            {ticket.subject}
                        </p>
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {ticket.requesterName} · via {ticket.source}
                        {ticket.assignee && (
                            <> · assigned to {ticket.assignee}</>
                        )}
                    </p>
                    {ticket.aiWorking ? (
                        <p className="mt-2 flex items-center gap-1.5 text-sm text-slate-500">
                            <Spinner className="size-3.5 text-brand" />
                            AI is reading this ticket…
                        </p>
                    ) : summary ? (
                        <p className="mt-2 line-clamp-2 text-sm text-slate-600">
                            {ticket.summary ? (
                                summary
                            ) : (
                                <AiSuggested>{summary}</AiSuggested>
                            )}
                        </p>
                    ) : null}
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2 sm:w-64 sm:justify-end">
                    {category && <CategoryBadge category={category} />}
                    {suggestedCategory && (
                        <AiSuggested>{suggestedCategory.label}</AiSuggested>
                    )}
                    {priority && (
                        <PriorityBadge
                            value={priority.value}
                            label={priority.label}
                        />
                    )}
                    {suggestedPriority && (
                        <AiSuggested>{suggestedPriority.label}</AiSuggested>
                    )}
                    {sentiment && (
                        <SentimentLabel
                            value={sentiment.value}
                            label={sentiment.label}
                        />
                    )}
                    {ticket.pendingSuggestions > 0 && (
                        <Badge tone="brand">
                            <SparklesIcon className="size-3" />
                            {ticket.pendingSuggestions} to review
                        </Badge>
                    )}
                    <RelativeTime
                        iso={ticket.lastActivityAt}
                        className="w-full text-right text-xs text-slate-400 sm:w-auto"
                    />
                </div>
            </Link>
        </li>
    );
}

export default function TicketsIndex({
    tickets,
    filters,
    statusCounts,
    categories,
    priorities,
    contactFormUrl,
}: {
    tickets: Paginated<TicketListItem>;
    filters: Filters;
    statusCounts: Record<string, number>;
    categories: CategoryBadgeData[];
    priorities: Option[];
    contactFormUrl: string;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [copied, setCopied] = useState(false);
    const isFirstRender = useRef(true);
    const hasAiWork = tickets.data.some((ticket) => ticket.aiWorking);

    const { start, stop } = usePoll(
        4000,
        { only: ['tickets', 'statusCounts'] },
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
            ticketsIndex.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['tickets', 'filters', 'statusCounts'],
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

    function copyContactLink() {
        void navigator.clipboard.writeText(contactFormUrl).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    }

    const totalAll = Object.values(statusCounts).reduce(
        (sum, count) => sum + count,
        0,
    );
    const hasFilters = Boolean(
        filters.priority || filters.category || filters.search,
    );

    return (
        <>
            <AppHead title="Tickets" />
            <PageHeader
                title="Tickets"
                description="Every ticket arrives with AI suggestions. Nothing changes or gets sent until your team approves it."
                actions={
                    <>
                        <Button variant="secondary" onClick={copyContactLink}>
                            {copied ? 'Link copied' : 'Copy contact form link'}
                        </Button>
                        <Link
                            href={inboundEmail()}
                            className={buttonClasses('primary')}
                        >
                            Paste an email
                        </Link>
                    </>
                }
            />

            <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
                {statusTabs.map((tab) => {
                    const count =
                        tab.value === 'all'
                            ? totalAll
                            : (statusCounts[tab.value] ?? 0);
                    const isActive = filters.status === tab.value;

                    return (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => applyFilters({ status: tab.value })}
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

            <div className="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
                <Input
                    type="search"
                    placeholder="Search subject, customer, summary or #number"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    aria-label="Search tickets"
                />
                <Select
                    aria-label="Filter by priority"
                    value={filters.priority ?? ''}
                    onChange={(event) =>
                        applyFilters({ priority: event.target.value || null })
                    }
                >
                    <option value="">Any priority</option>
                    {priorities.map((priority) => (
                        <option key={priority.value} value={priority.value}>
                            {priority.label}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label="Filter by category"
                    value={filters.category ?? ''}
                    onChange={(event) =>
                        applyFilters({
                            category: event.target.value
                                ? Number(event.target.value)
                                : null,
                        })
                    }
                >
                    <option value="">Any category</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label="Sort tickets"
                    value={filters.sort}
                    onChange={(event) =>
                        applyFilters({
                            sort: event.target.value as Filters['sort'],
                        })
                    }
                >
                    <option value="recent">Most recent activity</option>
                    <option value="priority">Highest priority first</option>
                </Select>
            </div>

            <Card>
                {tickets.data.length === 0 ? (
                    hasFilters ? (
                        <EmptyState
                            icon={InboxIcon}
                            title="No tickets match these filters"
                            description="Try a different search or clear the filters."
                            action={
                                <Button
                                    variant="secondary"
                                    onClick={() => {
                                        setSearch('');
                                        applyFilters({
                                            priority: null,
                                            category: null,
                                            search: null,
                                        });
                                    }}
                                >
                                    Clear filters
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={InboxIcon}
                            title="No tickets here yet"
                            description="Tickets arrive from your public contact form, from pasted emails, or through the API. Try sending one through the contact form to watch the AI triage it."
                            action={
                                <a
                                    href={contactFormUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className={buttonClasses('primary')}
                                >
                                    Open the contact form
                                </a>
                            }
                        />
                    )
                ) : (
                    <>
                        <ul className="divide-y divide-slate-100">
                            {tickets.data.map((ticket) => (
                                <TicketRow key={ticket.id} ticket={ticket} />
                            ))}
                        </ul>
                        <Pagination paginator={tickets} only={['tickets']} />
                    </>
                )}
            </Card>
        </>
    );
}
