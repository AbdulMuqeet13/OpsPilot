import { router } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { BarListChart } from '@/components/charts/bar-list-chart';
import { ChartCard, DataTable } from '@/components/charts/chart-card';
import { shortDate } from '@/components/charts/chart-theme';
import { DailyColumnChart } from '@/components/charts/daily-column-chart';
import {
    SentimentLineChart,
    sentimentWord,
} from '@/components/charts/sentiment-line-chart';
import { InsightsTabs } from '@/components/insights-tabs';
import { PageHeader } from '@/components/page-header';
import { StatTile } from '@/components/stat-tile';
import { formatInteger, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as insights } from '@/routes/insights';

type Props = {
    days: number;
    ranges: number[];
    summary: {
        tickets: number;
        leads: number;
        averageSentiment: number | null;
        hotLeadShare: number | null;
    };
    ticketsByCategory: { label: string; value: number }[];
    dailyTicketVolume: { date: string; value: number }[];
    dailySentiment: { date: string; value: number | null; tickets: number }[];
    leadScores: { key: string; label: string; value: number }[];
};

export default function Insights({
    days,
    ranges,
    summary,
    ticketsByCategory,
    dailyTicketVolume,
    dailySentiment,
    leadScores,
}: Props) {
    const [loading, setLoading] = useState(false);
    const totalLeads = leadScores.reduce((sum, row) => sum + row.value, 0);
    const hasSentiment = dailySentiment.some((row) => row.value !== null);

    function selectRange(range: number) {
        router.get(
            insights.url(),
            { days: range },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <AppHead title="Insights" />
            <PageHeader
                title="Insights"
                description="How support and sales are trending. Every number comes straight from your data."
            />
            <InsightsTabs />

            <div
                className="mb-6 flex items-center gap-1"
                role="group"
                aria-label="Date range"
            >
                {ranges.map((range) => (
                    <button
                        key={range}
                        type="button"
                        aria-pressed={range === days}
                        onClick={() => selectRange(range)}
                        className={cn(
                            'rounded-lg px-3 py-1.5 text-sm font-medium',
                            range === days
                                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-500 hover:bg-white/70 hover:text-slate-800',
                        )}
                    >
                        Last {range} days
                    </button>
                ))}
            </div>

            <div
                className={cn(
                    'space-y-6 transition-opacity',
                    loading && 'opacity-60',
                )}
            >
                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <StatTile
                        label="New tickets"
                        value={formatInteger(summary.tickets)}
                        hint={`Last ${days} days`}
                    />
                    <StatTile
                        label="Average sentiment"
                        value={
                            summary.averageSentiment === null
                                ? '—'
                                : `${summary.averageSentiment > 0 ? '+' : ''}${summary.averageSentiment.toFixed(2)}`
                        }
                        hint={
                            summary.averageSentiment === null
                                ? 'No triaged tickets yet'
                                : `${sentimentWord(summary.averageSentiment)}, on a −1 to +1 scale`
                        }
                    />
                    <StatTile
                        label="New leads"
                        value={formatInteger(summary.leads)}
                        hint={`Last ${days} days`}
                    />
                    <StatTile
                        label="Hot lead share"
                        value={
                            summary.hotLeadShare === null
                                ? '—'
                                : formatPercent(summary.hotLeadShare)
                        }
                        hint="Of scored leads"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <ChartCard
                        title="Tickets by category"
                        description={`Reviewed categories, last ${days} days`}
                        isEmpty={ticketsByCategory.length === 0}
                        emptyMessage="No tickets in this period yet."
                        chart={
                            <BarListChart
                                data={ticketsByCategory}
                                valueLabel={(value) =>
                                    `${value} ticket${value === 1 ? '' : 's'}`
                                }
                            />
                        }
                        table={
                            <DataTable
                                columns={['Category', 'Tickets']}
                                rows={ticketsByCategory.map((row) => [
                                    row.label,
                                    formatInteger(row.value),
                                ])}
                            />
                        }
                    />
                    <ChartCard
                        title="Daily ticket volume"
                        description="New tickets per day, all channels"
                        isEmpty={summary.tickets === 0}
                        emptyMessage="No tickets in this period yet."
                        chart={
                            <DailyColumnChart
                                data={dailyTicketVolume}
                                unit="tickets"
                            />
                        }
                        table={
                            <DataTable
                                columns={['Day', 'Tickets']}
                                rows={dailyTicketVolume.map((row) => [
                                    shortDate(row.date),
                                    formatInteger(row.value),
                                ])}
                            />
                        }
                    />
                    <ChartCard
                        title="Average customer sentiment"
                        description="Per day, from −1 (negative) to +1 (positive)"
                        isEmpty={!hasSentiment}
                        emptyMessage="Sentiment appears once tickets have been triaged."
                        chart={<SentimentLineChart data={dailySentiment} />}
                        table={
                            <DataTable
                                columns={['Day', 'Average']}
                                rows={dailySentiment
                                    .filter((row) => row.value !== null)
                                    .map((row) => [
                                        shortDate(row.date),
                                        `${(row.value ?? 0) > 0 ? '+' : ''}${(row.value ?? 0).toFixed(2)} (${row.tickets})`,
                                    ])}
                            />
                        }
                    />
                    <ChartCard
                        title="Lead score distribution"
                        description={`${formatInteger(totalLeads)} leads, last ${days} days`}
                        isEmpty={totalLeads === 0}
                        emptyMessage="No leads in this period yet."
                        chart={
                            <BarListChart
                                data={leadScores}
                                valueLabel={(value) =>
                                    `${value} lead${value === 1 ? '' : 's'} (${totalLeads > 0 ? Math.round((value / totalLeads) * 100) : 0}%)`
                                }
                            />
                        }
                        table={
                            <DataTable
                                columns={['Score', 'Leads']}
                                rows={leadScores.map((row) => [
                                    row.label,
                                    formatInteger(row.value),
                                ])}
                            />
                        }
                    />
                </div>
            </div>
        </>
    );
}
