import { AlertIcon, CheckCircleIcon } from '@/components/icons';
import { formatUsd } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Fill shifts brand → amber → rose as spend nears the cap; the track is a lighter step of the same hue.
 */
export function BudgetMeter({ spent, cap }: { spent: number; cap: number }) {
    const fraction = cap > 0 ? Math.min(spent / cap, 1) : 0;
    const state = fraction >= 1 ? 'reached' : fraction >= 0.75 ? 'near' : 'ok';

    const styles = {
        ok: {
            track: 'bg-brand-100',
            fill: 'bg-brand',
            label: 'Within budget',
            Icon: CheckCircleIcon,
            text: 'text-slate-600',
        },
        near: {
            track: 'bg-amber-100',
            fill: 'bg-amber-500',
            label: 'Nearly used up',
            Icon: AlertIcon,
            text: 'text-amber-800',
        },
        reached: {
            track: 'bg-rose-100',
            fill: 'bg-rose-600',
            label: 'Paused until tomorrow',
            Icon: AlertIcon,
            text: 'text-rose-700',
        },
    }[state];

    return (
        <div>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-2xl font-semibold tracking-tight text-slate-900">
                    {formatUsd(spent)}
                    <span className="ml-1 text-sm font-normal text-slate-500">
                        of {formatUsd(cap)} today
                    </span>
                </p>
                <p
                    className={cn(
                        'flex items-center gap-1 text-xs font-medium',
                        styles.text,
                    )}
                >
                    <styles.Icon className="size-3.5" />
                    {styles.label}
                </p>
            </div>
            <div
                role="meter"
                aria-label="Daily AI spend"
                aria-valuemin={0}
                aria-valuemax={cap}
                aria-valuenow={Math.min(spent, cap)}
                className={cn(
                    'mt-3 h-2 overflow-hidden rounded-full',
                    styles.track,
                )}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width]',
                        styles.fill,
                    )}
                    style={{
                        width: `${Math.max(fraction * 100, spent > 0 ? 2 : 0)}%`,
                    }}
                />
            </div>
        </div>
    );
}
