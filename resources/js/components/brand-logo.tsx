import { initials } from '@/lib/brand';
import { cn } from '@/lib/utils';
import type { Brand } from '@/types';

export function BrandLogo({
    brand,
    className,
    showName = true,
    nameClassName,
    size = 'md',
}: {
    brand: Pick<Brand, 'name' | 'logoUrl'>;
    className?: string;
    showName?: boolean;
    nameClassName?: string;
    size?: 'md' | 'lg';
}) {
    const markSize = size === 'lg' ? 'size-14 text-base' : 'size-8 text-xs';

    return (
        <span className={cn('flex min-w-0 items-center gap-2.5', className)}>
            {brand.logoUrl ? (
                <img
                    src={brand.logoUrl}
                    alt={showName ? '' : brand.name}
                    className={cn(
                        'shrink-0 rounded-lg object-contain',
                        markSize,
                    )}
                />
            ) : (
                <span
                    aria-hidden={showName}
                    className={cn(
                        'flex shrink-0 items-center justify-center rounded-lg bg-brand font-bold text-brand-foreground',
                        markSize,
                    )}
                >
                    {initials(brand.name)}
                </span>
            )}
            {showName && (
                <span
                    className={cn(
                        'truncate text-base font-semibold text-slate-900',
                        nameClassName,
                    )}
                >
                    {brand.name}
                </span>
            )}
        </span>
    );
}
