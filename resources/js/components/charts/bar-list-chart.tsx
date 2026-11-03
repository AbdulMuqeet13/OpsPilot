import {
    Bar,
    BarChart,
    Cell,
    LabelList,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartTooltipContent } from '@/components/charts/chart-tooltip';
import { axisTick, chartTheme } from '@/components/charts/chart-theme';

type Row = { label: string; value: number };

/**
 * Buckets that aren't real categories are drawn in a muted gray so they don't read as one.
 */
const mutedLabels = new Set(['Awaiting review', 'Uncategorised', 'Not scored']);

/**
 * Horizontal bars for comparing categories, each labelled with its value at the tip.
 */
export function BarListChart({
    data,
    valueLabel,
}: {
    data: Row[];
    valueLabel: (value: number) => string;
}) {
    const height = Math.max(160, data.length * 36 + 24);

    return (
        <div
            style={{ height }}
            role="img"
            aria-label={data
                .map((row) => `${row.label}: ${valueLabel(row.value)}`)
                .join(', ')}
        >
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={data}
                    layout="vertical"
                    margin={{ top: 4, right: 40, bottom: 4, left: 8 }}
                    barCategoryGap={8}
                >
                    <XAxis type="number" hide domain={[0, 'dataMax']} />
                    <YAxis
                        type="category"
                        dataKey="label"
                        width={130}
                        tickLine={false}
                        axisLine={false}
                        tick={{ ...axisTick, fill: chartTheme.textSecondary }}
                    />
                    <Tooltip
                        cursor={{ fill: 'rgba(15, 23, 42, 0.04)' }}
                        content={({ active, payload }) => {
                            const row = payload?.[0]?.payload as
                                | Row
                                | undefined;

                            return active && row ? (
                                <ChartTooltipContent
                                    label={row.label}
                                    value={valueLabel(row.value)}
                                />
                            ) : null;
                        }}
                    />
                    <Bar
                        dataKey="value"
                        fill={chartTheme.series}
                        radius={[0, 4, 4, 0]}
                        maxBarSize={24}
                        isAnimationActive={false}
                    >
                        {data.map((row) => (
                            <Cell
                                key={row.label}
                                fill={
                                    mutedLabels.has(row.label)
                                        ? chartTheme.axis
                                        : chartTheme.series
                                }
                            />
                        ))}
                        <LabelList
                            dataKey="value"
                            position="right"
                            offset={8}
                            fill={chartTheme.textSecondary}
                            fontSize={12}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
