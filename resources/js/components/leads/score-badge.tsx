import { cn } from '@/lib/utils';

const styles: Record<string, { classes: string; dot: string }> = {
    hot: {
        classes: 'bg-rose-50 text-rose-700 ring-rose-200',
        dot: 'bg-rose-500',
    },
    warm: {
        classes: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
    },
    cold: { classes: 'bg-sky-50 text-sky-700 ring-sky-200', dot: 'bg-sky-500' },
};

/**
 * Lead score with its label always visible, so it never relies on color alone.
 */
export function ScoreBadge({
    value,
    label,
    className,
}: {
    value: string;
    label: string;
    className?: string;
}) {
    const style = styles[value] ?? styles.cold;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset',
                style.classes,
                className,
            )}
        >
            <span className={cn('size-1.5 rounded-full', style.dot)} />
            {label}
        </span>
    );
}
