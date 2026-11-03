import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';

export function StatTile({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
}) {
    return (
        <Card className="p-5">
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                {value}
            </p>
            {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
        </Card>
    );
}
