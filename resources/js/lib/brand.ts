import type { CSSProperties } from 'react';
import type { Brand } from '@/types';

/**
 * CSS custom properties consumed by the brand-* Tailwind tokens in app.css.
 */
export function brandStyle(
    brand: Pick<Brand, 'primaryColor' | 'primaryForegroundColor'>,
): CSSProperties {
    return {
        '--brand': brand.primaryColor,
        '--brand-foreground': brand.primaryForegroundColor,
    } as CSSProperties;
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
