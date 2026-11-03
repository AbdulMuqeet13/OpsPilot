import type { ButtonHTMLAttributes } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

const variants = {
    primary:
        'bg-brand text-brand-foreground shadow-sm hover:bg-brand-700 focus-visible:outline-brand',
    secondary:
        'bg-white text-slate-700 shadow-sm ring-1 ring-slate-300 ring-inset hover:bg-slate-50 focus-visible:outline-brand',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-brand',
    danger: 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:outline-rose-600',
} as const;

const sizes = {
    sm: 'h-8 gap-1.5 px-3 text-xs',
    md: 'h-10 gap-2 px-4 text-sm',
    lg: 'h-11 gap-2 px-5 text-sm',
} as const;

export type ButtonVariant = keyof typeof variants;
export type ButtonSize = keyof typeof sizes;

export function buttonClasses(
    variant: ButtonVariant = 'primary',
    size: ButtonSize = 'md',
    className?: string,
): string {
    return cn(
        'inline-flex items-center justify-center rounded-lg font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-60',
        variants[variant],
        sizes[size],
        className,
    );
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
};

export function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    className,
    children,
    disabled,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            className={buttonClasses(variant, size, className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && <Spinner />}
            {children}
        </button>
    );
}
