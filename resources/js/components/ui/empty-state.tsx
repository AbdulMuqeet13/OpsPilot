import type { ComponentType, ReactNode } from 'react';
import type { IconProps } from '@/components/icons';

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: ComponentType<IconProps>;
    title: string;
    description: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand">
                <Icon className="size-6" />
            </div>
            <h3 className="mt-4 text-sm font-semibold text-slate-900">
                {title}
            </h3>
            <p className="mt-1 max-w-sm text-sm text-slate-500">
                {description}
            </p>
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
