import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppHead } from '@/components/app-head';
import { AlertIcon, SparklesIcon } from '@/components/icons';
import { InsightsTabs } from '@/components/insights-tabs';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { cn } from '@/lib/utils';
import {
    index as briefsIndex,
    store as generateBrief,
} from '@/routes/daily-briefs';
import { show as showLead } from '@/routes/leads';
import { show as showTicket } from '@/routes/tickets';

type Brief = {
    id: number;
    date: string;
    trigger: string;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'skipped';
    error: string | null;
    stats: {
        tickets: number;
        leads: number;
        hot_leads: number;
        urgent_open: number;
        average_sentiment: number | null;
        by_category: Record<string, number>;
    } | null;
    content: {
        headline: string;
        summary: string;
        themes: {
            title: string;
            detail: string;
            tickets: { id: number; number: number }[];
        }[];
        urgent_items: {
            kind: 'ticket' | 'lead';
            id: number;
            label: string;
            reason: string;
        }[];
    } | null;
    generatedAt: string | null;
    usedAi: boolean;
};

function longDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date(`${iso}T00:00:00`));
}

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-200">
            <p className="text-lg font-semibold text-slate-900">{value}</p>
            <p className="text-xs text-slate-500">{label}</p>
        </div>
    );
}

export default function Briefs({
    brief,
    history,
    today,
    yesterday,
}: {
    brief: Brief | null;
    history: { date: string; headline: string | null; status: string }[];
    today: string;
    yesterday: string;
}) {
    const [requesting, setRequesting] = useState<string | null>(null);
    const isGenerating =
        brief?.status === 'pending' || brief?.status === 'processing';
    const { start, stop } = usePoll(
        3000,
        { only: ['brief', 'history'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isGenerating) {
            start();
        } else {
            stop();
        }
    }, [isGenerating, start, stop]);

    function generate(period: 'yesterday' | 'today') {
        router.post(
            generateBrief.url(),
            { period },
            {
                onStart: () => setRequesting(period),
                onFinish: () => setRequesting(null),
            },
        );
    }

    const generateButtons = (
        <>
            <Button
                variant="secondary"
                loading={requesting === 'today'}
                disabled={isGenerating}
                onClick={() => generate('today')}
            >
                Brief me on today so far
            </Button>
            <Button
                loading={requesting === 'yesterday'}
                disabled={isGenerating}
                onClick={() => generate('yesterday')}
            >
                <SparklesIcon className="size-4" />
                {history.some((item) => item.date === yesterday)
                    ? "Regenerate yesterday's brief"
                    : "Generate yesterday's brief"}
            </Button>
        </>
    );

    return (
        <>
            <AppHead title="Daily brief" />
            <PageHeader
                title="Insights"
                description="A short AI summary of each day, written automatically every morning at 6:00."
                actions={generateButtons}
            />
            <InsightsTabs />

            {brief === null ? (
                <Card>
                    <EmptyState
                        icon={SparklesIcon}
                        title="No briefs yet"
                        description="The first brief is written tomorrow morning. Generate one now to see yesterday's tickets and leads summarised, with recurring themes and urgent items."
                        action={
                            <div className="flex flex-wrap justify-center gap-2">
                                {generateButtons}
                            </div>
                        }
                    />
                </Card>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <div className="min-w-0 space-y-6">
                        <Card>
                            <CardBody className="space-y-5">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                        {brief.date === today
                                            ? 'Today so far'
                                            : longDate(brief.date)}
                                    </p>
                                    {isGenerating ? (
                                        <div
                                            className="mt-3 animate-pulse space-y-3"
                                            aria-live="polite"
                                        >
                                            <p className="flex items-center gap-2 text-sm text-slate-500">
                                                <SparklesIcon className="size-4 text-brand" />{' '}
                                                Reading the day's tickets and
                                                leads…
                                            </p>
                                            <div className="h-6 w-3/4 rounded bg-slate-200" />
                                            <div className="h-3 w-full rounded bg-slate-100" />
                                            <div className="h-3 w-5/6 rounded bg-slate-100" />
                                        </div>
                                    ) : brief.content ? (
                                        <>
                                            <h2 className="mt-1 text-xl font-semibold tracking-tight text-slate-900">
                                                {brief.content.headline}
                                            </h2>
                                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                                {brief.content.summary}
                                            </p>
                                        </>
                                    ) : null}
                                </div>

                                {brief.status === 'failed' && (
                                    <Alert
                                        tone="error"
                                        title="This brief couldn't be written"
                                    >
                                        {brief.error}
                                    </Alert>
                                )}

                                {brief.stats && !isGenerating && (
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <Stat
                                            label="New tickets"
                                            value={brief.stats.tickets}
                                        />
                                        <Stat
                                            label="New leads"
                                            value={brief.stats.leads}
                                        />
                                        <Stat
                                            label="Hot leads"
                                            value={brief.stats.hot_leads}
                                        />
                                        <Stat
                                            label="Urgent still open"
                                            value={brief.stats.urgent_open}
                                        />
                                    </div>
                                )}

                                {brief.generatedAt && !isGenerating && (
                                    <p className="text-xs text-slate-400">
                                        {brief.trigger}{' '}
                                        <RelativeTime iso={brief.generatedAt} />
                                        {!brief.usedAi &&
                                            ' · quiet day, written without AI'}
                                    </p>
                                )}
                            </CardBody>
                        </Card>

                        {brief.content &&
                            !isGenerating &&
                            brief.content.urgent_items.length > 0 && (
                                <Card>
                                    <CardHeader
                                        title={
                                            <span className="flex items-center gap-2">
                                                <AlertIcon className="size-4 text-rose-600" />{' '}
                                                Act on these today
                                            </span>
                                        }
                                    />
                                    <ul className="divide-y divide-slate-100">
                                        {brief.content.urgent_items.map(
                                            (item) => (
                                                <li
                                                    key={`${item.kind}-${item.id}`}
                                                >
                                                    <Link
                                                        href={
                                                            item.kind ===
                                                            'ticket'
                                                                ? showTicket(
                                                                      item.id,
                                                                  )
                                                                : showLead(
                                                                      item.id,
                                                                  )
                                                        }
                                                        className="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 sm:px-6"
                                                    >
                                                        <Badge
                                                            tone={
                                                                item.kind ===
                                                                'ticket'
                                                                    ? 'info'
                                                                    : 'brand'
                                                            }
                                                        >
                                                            {item.kind ===
                                                            'ticket'
                                                                ? 'Ticket'
                                                                : 'Lead'}
                                                        </Badge>
                                                        <span className="min-w-0">
                                                            <span className="block truncate text-sm font-medium text-slate-900">
                                                                {item.label}
                                                            </span>
                                                            <span className="block text-sm text-slate-500">
                                                                {item.reason}
                                                            </span>
                                                        </span>
                                                    </Link>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </Card>
                            )}

                        {brief.content &&
                            !isGenerating &&
                            brief.content.themes.length > 0 && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {brief.content.themes.map((theme) => (
                                        <Card key={theme.title} className="p-5">
                                            <p className="text-sm font-semibold text-slate-900">
                                                {theme.title}
                                            </p>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {theme.detail}
                                            </p>
                                            {theme.tickets.length > 0 && (
                                                <p className="mt-3 flex flex-wrap gap-1.5">
                                                    {theme.tickets.map(
                                                        (ticket) => (
                                                            <Link
                                                                key={ticket.id}
                                                                href={showTicket(
                                                                    ticket.id,
                                                                )}
                                                                className="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700 tabular-nums hover:bg-slate-200"
                                                            >
                                                                #{ticket.number}
                                                            </Link>
                                                        ),
                                                    )}
                                                </p>
                                            )}
                                        </Card>
                                    ))}
                                </div>
                            )}
                    </div>

                    <Card className="self-start">
                        <CardHeader title="Recent briefs" />
                        <ul className="py-1">
                            {history.map((item) => (
                                <li key={item.date}>
                                    <Link
                                        href={briefsIndex({
                                            query: { date: item.date },
                                        })}
                                        preserveScroll
                                        className={cn(
                                            'block px-5 py-2.5 hover:bg-slate-50',
                                            item.date === brief.date &&
                                                'bg-brand-50/60',
                                        )}
                                    >
                                        <span className="block text-xs font-medium text-slate-500">
                                            {item.date === today
                                                ? 'Today'
                                                : longDate(item.date)}
                                        </span>
                                        <span className="block truncate text-sm text-slate-800">
                                            {item.headline ??
                                                (item.status === 'failed'
                                                    ? 'Failed'
                                                    : 'Generating…')}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}
        </>
    );
}
