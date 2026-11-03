import { Link, usePage } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { CheckCircleIcon, SparklesIcon } from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import { StatTile } from '@/components/stat-tile';
import { Badge } from '@/components/ui/badge';
import { buttonClasses } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { formatInteger, formatPercent, formatUsd } from '@/lib/format';
import { index as aiActivity } from '@/routes/ai-activity';
import { index as aiUsage } from '@/routes/ai-usage';
import { index as dailyBriefs } from '@/routes/daily-briefs';
import { index as leadsIndex, show as showLead } from '@/routes/leads';
import { index as ticketsIndex, show as showTicket } from '@/routes/tickets';

type Stats = {
    openTickets: number;
    awaitingReview: number;
    hotLeadsToContact: number;
    acceptanceRate: number | null;
    decided: number;
};

type AttentionItem = {
    kind: 'ticket' | 'lead';
    id: number;
    title: string;
    detail: string;
    badge: string;
    at: string | null;
};

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
}

export default function Dashboard({
    stats,
    attention,
    latestBrief,
    aiSpendToday,
}: {
    stats: Stats;
    attention: AttentionItem[];
    latestBrief: {
        date: string;
        headline: string | null;
        summary: string | null;
    } | null;
    aiSpendToday: number | null;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0];

    return (
        <>
            <AppHead title="Dashboard" />
            <PageHeader
                title={`${greeting()}, ${firstName}`}
                description="Here's what needs a human decision today."
            />

            <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <Link
                    href={ticketsIndex()}
                    className="rounded-xl focus-visible:outline-2 focus-visible:outline-brand"
                >
                    <StatTile
                        label="Open tickets"
                        value={formatInteger(stats.openTickets)}
                        hint="Across all channels"
                    />
                </Link>
                <Link
                    href={aiActivity({ query: { decision: 'pending' } })}
                    className="rounded-xl focus-visible:outline-2 focus-visible:outline-brand"
                >
                    <StatTile
                        label="AI suggestions to review"
                        value={formatInteger(stats.awaitingReview)}
                        hint="Waiting for accept, edit or reject"
                    />
                </Link>
                <Link
                    href={leadsIndex({
                        query: { score: 'hot', status: 'new' },
                    })}
                    className="rounded-xl focus-visible:outline-2 focus-visible:outline-brand"
                >
                    <StatTile
                        label="Hot leads to contact"
                        value={formatInteger(stats.hotLeadsToContact)}
                        hint="Scored hot, not contacted yet"
                    />
                </Link>
                <Link
                    href={aiActivity()}
                    className="rounded-xl focus-visible:outline-2 focus-visible:outline-brand"
                >
                    <StatTile
                        label="AI acceptance rate"
                        value={
                            stats.acceptanceRate === null
                                ? '—'
                                : formatPercent(stats.acceptanceRate)
                        }
                        hint={
                            stats.decided > 0
                                ? `${formatInteger(stats.decided)} decisions, last 30 days`
                                : 'No decisions yet'
                        }
                    />
                </Link>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader
                        title="Needs attention"
                        description="Urgent tickets and hot leads, including ones the AI has flagged but nobody has reviewed yet."
                    />
                    {attention.length === 0 ? (
                        <EmptyState
                            icon={CheckCircleIcon}
                            title="All clear"
                            description="No urgent tickets or uncontacted hot leads right now."
                        />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {attention.map((item) => (
                                <li key={`${item.kind}-${item.id}`}>
                                    <Link
                                        href={
                                            item.kind === 'ticket'
                                                ? showTicket(item.id)
                                                : showLead(item.id)
                                        }
                                        className="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 sm:px-6"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-slate-900">
                                                {item.title}
                                            </p>
                                            <p className="truncate text-sm text-slate-500">
                                                {item.detail}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <Badge
                                                tone={
                                                    item.kind === 'lead'
                                                        ? 'brand'
                                                        : item.badge ===
                                                            'Urgent'
                                                          ? 'danger'
                                                          : 'warning'
                                                }
                                            >
                                                {item.badge}
                                            </Badge>
                                            <p className="mt-1 text-xs text-slate-400">
                                                <RelativeTime iso={item.at} />
                                            </p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader
                            title={
                                <span className="flex items-center gap-2">
                                    <SparklesIcon className="size-4 text-brand" />{' '}
                                    Daily brief
                                </span>
                            }
                        />
                        <CardBody>
                            {latestBrief?.headline ? (
                                <>
                                    <p className="text-xs text-slate-500">
                                        {new Intl.DateTimeFormat('en-US', {
                                            weekday: 'long',
                                            month: 'short',
                                            day: 'numeric',
                                        }).format(
                                            new Date(
                                                `${latestBrief.date}T00:00:00`,
                                            ),
                                        )}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold text-slate-900">
                                        {latestBrief.headline}
                                    </p>
                                    <p className="mt-2 line-clamp-4 text-sm text-slate-600">
                                        {latestBrief.summary}
                                    </p>
                                    <Link
                                        href={dailyBriefs()}
                                        className="mt-3 inline-block text-sm font-medium text-brand hover:underline"
                                    >
                                        Read the full brief
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm text-slate-600">
                                        A summary of yesterday's tickets and
                                        leads, with themes and urgent items, is
                                        written every morning.
                                    </p>
                                    <Link
                                        href={dailyBriefs()}
                                        className={`${buttonClasses('secondary', 'sm')} mt-3`}
                                    >
                                        Generate one now
                                    </Link>
                                </>
                            )}
                        </CardBody>
                    </Card>

                    {aiSpendToday !== null && (
                        <Card>
                            <CardBody className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-slate-500">
                                        AI spend today
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                                        {formatUsd(aiSpendToday)}
                                    </p>
                                </div>
                                <Link
                                    href={aiUsage()}
                                    className="text-sm font-medium text-brand hover:underline"
                                >
                                    Usage details
                                </Link>
                            </CardBody>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
