import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export const fieldClasses =
    'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-brand focus:outline-none disabled:bg-slate-50 disabled:text-slate-500 aria-invalid:ring-rose-400';

export function Input({
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return <input className={cn(fieldClasses, 'h-10', className)} {...props} />;
}
