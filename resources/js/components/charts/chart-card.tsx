import type { ReactNode } from 'react';
import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * A chart with a built-in table view, so every value is reachable without hovering.
 */
export function ChartCard({
    title,
    description,
    chart,
    table,
    isEmpty,
    emptyMessage,
    className,
}: {
    title: string;
    description?: string;
    chart: ReactNode;
    table: ReactNode;
    isEmpty: boolean;
    emptyMessage: string;
    className?: string;
}) {
    const [view, setView] = useState<'chart' | 'table'>('chart');

    return (
        <Card className={cn('flex flex-col', className)}>
            <div className="flex items-start justify-between gap-3 px-5 pt-4 sm:px-6">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-0.5 text-xs text-slate-500">
                            {description}
                        </p>
                    )}
                </div>
                {!isEmpty && (
                    <div
                        className="flex shrink-0 rounded-lg bg-slate-100 p-0.5 text-xs"
                        role="group"
                        aria-label={`${title} view`}
                    >
                        {(['chart', 'table'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-pressed={view === option}
                                onClick={() => setView(option)}
                                className={cn(
                                    'rounded-md px-2 py-1 font-medium capitalize',
                                    view === option
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700',
                                )}
                            >
                                {option}
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <div className="flex-1 px-3 pt-3 pb-4 sm:px-4">
                {isEmpty ? (
                    <p className="flex h-56 items-center justify-center px-6 text-center text-sm text-slate-400">
                        {emptyMessage}
                    </p>
                ) : view === 'chart' ? (
                    chart
                ) : (
                    <div className="max-h-64 overflow-y-auto px-2">{table}</div>
                )}
            </div>
        </Card>
    );
}

export function DataTable({
    columns,
    rows,
}: {
    columns: [string, string];
    rows: [string, string][];
}) {
    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="border-b border-slate-100 text-left text-xs text-slate-500">
                    <th scope="col" className="py-2 font-medium">
                        {columns[0]}
                    </th>
                    <th scope="col" className="py-2 text-right font-medium">
                        {columns[1]}
                    </th>
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 tabular-nums">
                {rows.map(([label, value]) => (
                    <tr key={label}>
                        <th
                            scope="row"
                            className="py-1.5 text-left font-normal text-slate-700"
                        >
                            {label}
                        </th>
                        <td className="py-1.5 text-right text-slate-900">
                            {value}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
