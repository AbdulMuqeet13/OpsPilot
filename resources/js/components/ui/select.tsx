import type { SelectHTMLAttributes } from 'react';
import { fieldClasses } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function Select({
    className,
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select className={cn(fieldClasses, 'h-10 pr-8', className)} {...props}>
            {children}
        </select>
    );
}
