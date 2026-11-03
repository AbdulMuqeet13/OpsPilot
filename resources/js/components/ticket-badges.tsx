import type { ReactNode } from 'react';
import { SparklesIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { CategoryBadgeData } from '@/types';

const categoryColors: Record<string, string> = {
    blue: 'bg-blue-50 text-blue-700 ring-blue-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
    rose: 'bg-rose-50 text-rose-700 ring-rose-200',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
    orange: 'bg-orange-50 text-orange-700 ring-orange-200',
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
};

export const categorySwatches: Record<string, string> = {
    blue: 'bg-blue-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    violet: 'bg-violet-500',
    rose: 'bg-rose-500',
    cyan: 'bg-cyan-500',
    orange: 'bg-orange-500',
    slate: 'bg-slate-500',
};

export function CategoryBadge({
    category,
    className,
}: {
    category: Pick<CategoryBadgeData, 'name' | 'color'>;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                categoryColors[category.color] ?? categoryColors.slate,
                className,
            )}
        >
            {category.name}
        </span>
    );
}

const priorityTones = {
    urgent: 'danger',
    high: 'warning',
    normal: 'neutral',
    low: 'neutral',
} as const;

export function PriorityBadge({
    value,
    label,
}: {
    value: string;
    label: string;
}) {
    return (
        <Badge
            tone={
                priorityTones[value as keyof typeof priorityTones] ?? 'neutral'
            }
        >
            {value === 'urgent' && <span aria-hidden="true">●</span>}
            {label}
        </Badge>
    );
}

const sentimentDots: Record<string, string> = {
    positive: 'bg-emerald-500',
    neutral: 'bg-slate-400',
    negative: 'bg-rose-500',
};

/**
 * Sentiment always shows its label next to the dot, so it never relies on color alone.
 */
export function SentimentLabel({
    value,
    label,
}: {
    value: string;
    label: string;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-slate-600">
            <span
                className={cn(
                    'size-2 rounded-full',
                    sentimentDots[value] ?? 'bg-slate-400',
                )}
            />
            {label}
        </span>
    );
}

const statusTones = {
    open: 'info',
    pending: 'warning',
    resolved: 'success',
} as const;

export function StatusBadge({
    value,
    label,
}: {
    value: string;
    label: string;
}) {
    return (
        <Badge
            tone={statusTones[value as keyof typeof statusTones] ?? 'neutral'}
        >
            {label}
        </Badge>
    );
}

/**
 * Marks a value that came from the AI and hasn't been reviewed by a person yet.
 */
export function AiSuggested({ children }: { children: ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-md border border-dashed border-brand-200 bg-brand-50/60 px-1.5 py-0.5 text-xs text-brand-800">
            <SparklesIcon
                className="size-3 shrink-0 text-brand"
                aria-label="AI suggested"
            />
            {children}
        </span>
    );
}
