/**
 * Chart ink from the validated reference palette (light mode). Data marks use
 * the fixed series blue rather than the brand color, because a client's brand
 * color can be too pale to read as a data mark.
 */
export const chartTheme = {
    series: '#2a78d6',
    seriesWash: 'rgba(42, 120, 214, 0.10)',
    grid: '#e1e0d9',
    axis: '#c3c2b7',
    muted: '#898781',
    textSecondary: '#52514e',
    textPrimary: '#0b0b0b',
    surface: '#ffffff',
} as const;

export const axisTick = { fill: chartTheme.muted, fontSize: 12 } as const;

export function shortDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${iso}T00:00:00`));
}
