import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-xl bg-white shadow-sm ring-1 ring-slate-200',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({
    title,
    description,
    action,
}: {
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <h2 className="text-base font-semibold text-slate-900">
                    {title}
                </h2>
                {description && (
                    <p className="mt-0.5 text-sm text-slate-500">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}

export function CardBody({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-5 py-5 sm:px-6', className)} {...props} />;
}

export function CardFooter({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'flex items-center justify-end gap-3 rounded-b-xl border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6',
                className,
            )}
            {...props}
        />
    );
}
