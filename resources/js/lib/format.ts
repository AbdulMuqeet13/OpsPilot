const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const preciseUsd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
});

const integer = new Intl.NumberFormat('en-US');

/**
 * Cents for totals; four decimals for tiny per-call costs so they don't all read "$0.00".
 */
export function formatUsd(value: number): string {
    return value !== 0 && Math.abs(value) < 0.01
        ? preciseUsd.format(value)
        : usd.format(value);
}

export function formatInteger(value: number): string {
    return integer.format(value);
}

export function formatPercent(fraction: number): string {
    return `${Math.round(fraction * 1000) / 10}%`;
}

export function formatDuration(milliseconds: number): string {
    return milliseconds < 1000
        ? `${milliseconds} ms`
        : `${(milliseconds / 1000).toFixed(1)} s`;
}

export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}
