import { formatDateTime } from '@/lib/format';

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

export function relativeTime(iso: string): string {
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function RelativeTime({
    iso,
    className,
}: {
    iso: string | null;
    className?: string;
}) {
    if (!iso) {
        return null;
    }

    return (
        <time
            dateTime={iso}
            title={formatDateTime(iso)}
            className={className}
            suppressHydrationWarning
        >
            {relativeTime(iso)}
        </time>
    );
}
