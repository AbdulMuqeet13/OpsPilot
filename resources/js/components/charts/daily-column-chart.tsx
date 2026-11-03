import {
    Bar,
    BarChart,
    CartesianGrid,
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

type Row = { date: string; value: number };

export function DailyColumnChart({
    data,
    unit,
}: {
    data: Row[];
    unit: string;
}) {
    return (
        <div
            className="h-56"
            role="img"
            aria-label={`Daily ${unit} for ${data.length} days`}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={data}
                    margin={{ top: 8, right: 8, bottom: 0, left: -16 }}
                    barCategoryGap="20%"
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
                        allowDecimals={false}
                        tick={axisTick}
                        tickLine={false}
                        axisLine={false}
                        width={40}
                    />
                    <Tooltip
                        cursor={{ fill: 'rgba(15, 23, 42, 0.04)' }}
                        content={({ active, payload }) => {
                            const row = payload?.[0]?.payload as
                                | Row
                                | undefined;

                            return active && row ? (
                                <ChartTooltipContent
                                    label={shortDate(row.date)}
                                    value={`${row.value} ${unit}`}
                                />
                            ) : null;
                        }}
                    />
                    <Bar
                        dataKey="value"
                        fill={chartTheme.series}
                        radius={[4, 4, 0, 0]}
                        maxBarSize={24}
                        isAnimationActive={false}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
