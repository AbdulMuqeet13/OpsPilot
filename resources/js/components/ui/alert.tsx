import type { ReactNode } from 'react';
import { AlertIcon, CheckCircleIcon, InfoIcon } from '@/components/icons';
import { cn } from '@/lib/utils';

const tones = {
    info: { classes: 'bg-sky-50 text-sky-800 ring-sky-200', Icon: InfoIcon },
    success: {
        classes: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        Icon: CheckCircleIcon,
    },
    warning: {
        classes: 'bg-amber-50 text-amber-900 ring-amber-200',
        Icon: AlertIcon,
    },
    error: { classes: 'bg-rose-50 text-rose-800 ring-rose-200', Icon: AlertIcon },
} as const;

export function Alert({
    tone = 'info',
    title,
    children,
    className,
}: {
    tone?: keyof typeof tones;
    title?: string;
    children?: ReactNode;
    className?: string;
}) {
    const { classes, Icon } = tones[tone];

    return (
        <div
            role={tone === 'error' ? 'alert' : 'status'}
            className={cn(
                'flex gap-3 rounded-lg p-3.5 text-sm ring-1 ring-inset',
                classes,
                className,
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" />
            <div>
                {title && <p className="font-medium">{title}</p>}
                {children && (
                    <div className={cn(title && 'mt-0.5 opacity-90')}>
                        {children}
                    </div>
                )}
            </div>
        </div>
    );
}
