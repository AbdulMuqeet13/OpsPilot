import { chartTheme } from '@/components/charts/chart-theme';

/**
 * Tooltip body: the value leads, the label follows, keyed with a short line in the series color.
 */
export function ChartTooltipContent({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="rounded-lg bg-white px-3 py-2 text-xs shadow-lg ring-1 ring-slate-200">
            <p className="flex items-center gap-2">
                <span
                    className="h-0.5 w-3 rounded-full"
                    style={{ backgroundColor: chartTheme.series }}
                />
                <span className="text-sm font-semibold text-slate-900 tabular-nums">
                    {value}
                </span>
            </p>
            <p className="mt-0.5 text-slate-500">{label}</p>
            {detail && <p className="text-slate-400">{detail}</p>}
        </div>
    );
}
