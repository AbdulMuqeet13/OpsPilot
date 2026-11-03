import { AppHead } from '@/components/app-head';
import { BudgetMeter } from '@/components/budget-meter';
import { ActivityIcon } from '@/components/icons';
import { StatTile } from '@/components/stat-tile';
import { Badge } from '@/components/ui/badge';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import type { Paginated } from '@/components/ui/pagination';
import { Pagination } from '@/components/ui/pagination';
import {
    formatDateTime,
    formatDuration,
    formatInteger,
    formatPercent,
    formatUsd,
} from '@/lib/format';

type Summary = {
    todayCostUsd: number;
    last7DaysCostUsd: number;
    last7DaysCalls: number;
    last7DaysFailureRate: number;
    averageDurationMs: number;
};

type FeatureUsage = {
    feature: string;
    label: string;
    calls: number;
    inputTokens: number;
    outputTokens: number;
    costUsd: number;
};

type UsageCall = {
    id: number;
    feature: string;
    provider: string;
    model: string;
    inputTokens: number;
    outputTokens: number;
    costUsd: number;
    durationMs: number;
    status: 'succeeded' | 'failed' | 'refused';
    statusLabel: string;
    failureMessage: string | null;
    user: string | null;
    createdAt: string | null;
};

const statusTone = {
    succeeded: 'success',
    failed: 'danger',
    refused: 'warning',
} as const;

export default function AiUsage({
    summary,
    byFeature,
    recentCalls,
    demoBudget,
}: {
    summary: Summary;
    byFeature: FeatureUsage[];
    recentCalls: Paginated<UsageCall>;
    demoBudget: { capUsd: number; spentTodayUsd: number } | null;
}) {
    return (
        <>
            <AppHead title="AI usage" />

            {demoBudget && (
                <Card>
                    <CardHeader
                        title="Demo AI budget"
                        description="Shared by every demo sandbox. When it runs out, AI features pause until midnight and the rest of the app keeps working."
                    />
                    <CardBody>
                        <BudgetMeter
                            spent={demoBudget.spentTodayUsd}
                            cap={demoBudget.capUsd}
                        />
                    </CardBody>
                </Card>
            )}

            <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatTile
                    label="Spend today"
                    value={formatUsd(summary.todayCostUsd)}
                    hint="This workspace"
                />
                <StatTile
                    label="Spend, last 7 days"
                    value={formatUsd(summary.last7DaysCostUsd)}
                    hint={`${formatInteger(summary.last7DaysCalls)} AI calls`}
                />
                <StatTile
                    label="Failure rate"
                    value={formatPercent(summary.last7DaysFailureRate)}
                    hint="Last 7 days, after retries"
                />
                <StatTile
                    label="Average response"
                    value={formatDuration(summary.averageDurationMs)}
                    hint="Last 7 days"
                />
            </div>

            <Card>
                <CardHeader
                    title="Usage by feature"
                    description="Last 30 days."
                />
                {byFeature.length === 0 ? (
                    <EmptyState
                        icon={ActivityIcon}
                        title="No AI calls yet"
                        description="Every AI call is logged here with its tokens, cost and duration. Run the connection test on the AI features page to see the first one."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs font-medium text-slate-500">
                                    <th
                                        scope="col"
                                        className="px-5 py-2.5 sm:px-6"
                                    >
                                        Feature
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2.5 text-right"
                                    >
                                        Calls
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2.5 text-right"
                                    >
                                        Input tokens
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2.5 text-right"
                                    >
                                        Output tokens
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-5 py-2.5 text-right sm:px-6"
                                    >
                                        Cost
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 tabular-nums">
                                {byFeature.map((row) => (
                                    <tr key={row.feature}>
                                        <th
                                            scope="row"
                                            className="px-5 py-3 text-left font-medium text-slate-900 sm:px-6"
                                        >
                                            {row.label}
                                        </th>
                                        <td className="px-3 py-3 text-right text-slate-600">
                                            {formatInteger(row.calls)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-slate-600">
                                            {formatInteger(row.inputTokens)}
                                        </td>
                                        <td className="px-3 py-3 text-right text-slate-600">
                                            {formatInteger(row.outputTokens)}
                                        </td>
                                        <td className="px-5 py-3 text-right font-medium text-slate-900 sm:px-6">
                                            {formatUsd(row.costUsd)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card>
                <CardHeader
                    title="Recent AI calls"
                    description="Every call, including failed ones, with its estimated cost."
                />
                {recentCalls.data.length === 0 ? (
                    <EmptyState
                        icon={ActivityIcon}
                        title="Nothing logged yet"
                        description="AI calls will appear here as soon as they happen."
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
                                            When
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Feature
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Model
                                        </th>
                                        <th scope="col" className="px-3 py-2.5">
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2.5 text-right"
                                        >
                                            Tokens in / out
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2.5 text-right"
                                        >
                                            Time
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right sm:px-6"
                                        >
                                            Cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 tabular-nums">
                                    {recentCalls.data.map((call) => (
                                        <tr key={call.id} className="align-top">
                                            <td className="px-5 py-3 whitespace-nowrap text-slate-500 sm:px-6">
                                                {formatDateTime(call.createdAt)}
                                            </td>
                                            <td className="px-3 py-3">
                                                <p className="font-medium text-slate-900">
                                                    {call.feature}
                                                </p>
                                                {call.user && (
                                                    <p className="text-xs text-slate-500">
                                                        by {call.user}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <code className="font-mono text-xs text-slate-600">
                                                    {call.model}
                                                </code>
                                            </td>
                                            <td className="px-3 py-3">
                                                <Badge
                                                    tone={
                                                        statusTone[call.status]
                                                    }
                                                >
                                                    {call.statusLabel}
                                                </Badge>
                                                {call.failureMessage && (
                                                    <p className="mt-1 max-w-xs text-xs text-slate-500">
                                                        {call.failureMessage}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right whitespace-nowrap text-slate-600">
                                                {formatInteger(
                                                    call.inputTokens,
                                                )}{' '}
                                                /{' '}
                                                {formatInteger(
                                                    call.outputTokens,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right whitespace-nowrap text-slate-600">
                                                {formatDuration(
                                                    call.durationMs,
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right font-medium whitespace-nowrap text-slate-900 sm:px-6">
                                                {formatUsd(call.costUsd)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            paginator={recentCalls}
                            only={['recentCalls']}
                        />
                    </>
                )}
            </Card>
        </>
    );
}
