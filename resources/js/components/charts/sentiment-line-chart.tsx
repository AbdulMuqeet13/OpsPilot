import {
    CartesianGrid,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartTooltipContent } from '@/components/charts/chart-tooltip';
import {
    axisTick,
    chartTheme,
    shortDate,
} from '@/components/charts/chart-theme';

type Row = { date: string; value: number | null; tickets: number };

const sentimentTicks = [-1, -0.5, 0, 0.5, 1];

const tickLabels: Record<string, string> = {
    '-1': '−1',
    '-0.5': '−0.5',
    '0': '0',
    '0.5': '+0.5',
    '1': '+1',
};

export function sentimentWord(value: number): string {
    if (value <= -0.35) {
        return 'Negative';
    }

    if (value >= 0.35) {
        return 'Positive';
    }

    return 'Neutral';
}

function signed(value: number): string {
    return `${value > 0 ? '+' : ''}${value.toFixed(2)}`;
}

/**
 * Average sentiment per day on a fixed -1…+1 scale with the neutral line drawn; days without tickets are gaps.
 */
export function SentimentLineChart({ data }: { data: Row[] }) {
    return (
        <div
            className="h-56"
            role="img"
            aria-label="Average customer sentiment per day, from -1 (negative) to +1 (positive)"
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={data}
                    margin={{ top: 8, right: 12, bottom: 0, left: 0 }}
                >
                    <CartesianGrid vertical={false} stroke={chartTheme.grid} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={shortDate}
                        tick={axisTick}
                        tickLine={false}
                        axisLine={{ stroke: chartTheme.axis }}
                        minTickGap={24}
                    />
                    <YAxis
                        domain={[-1, 1]}
                        ticks={sentimentTicks}
                        tickFormatter={(value: number) =>
                            tickLabels[String(value)] ?? String(value)
                        }
                        tick={axisTick}
                        tickLine={false}
                        axisLine={false}
                        width={44}
                    />
                    <ReferenceLine y={0} stroke={chartTheme.axis} />
                    <Tooltip
                        cursor={{ stroke: chartTheme.axis, strokeWidth: 1 }}
                        content={({ active, payload }) => {
                            const row = payload?.[0]?.payload as
                                | Row
                                | undefined;

                            if (!active || !row) {
                                return null;
                            }

                            return row.value === null ? (
                                <ChartTooltipContent
                                    label={shortDate(row.date)}
                                    value="No triaged tickets"
                                />
                            ) : (
                                <ChartTooltipContent
                                    label={shortDate(row.date)}
                                    value={`${signed(row.value)} · ${sentimentWord(row.value)}`}
                                    detail={`${row.tickets} ticket${row.tickets === 1 ? '' : 's'}`}
                                />
                            );
                        }}
                    />
                    <Line
                        type="monotone"
                        dataKey="value"
                        stroke={chartTheme.series}
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        connectNulls={false}
                        dot={
                            data.length <= 14
                                ? {
                                      r: 4,
                                      fill: chartTheme.series,
                                      stroke: chartTheme.surface,
                                      strokeWidth: 2,
                                  }
                                : false
                        }
                        activeDot={{
                            r: 5,
                            fill: chartTheme.series,
                            stroke: chartTheme.surface,
                            strokeWidth: 2,
                        }}
                        isAnimationActive={false}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}
