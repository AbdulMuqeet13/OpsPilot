import { usePage } from '@inertiajs/react';
import { ClockIcon } from '@/components/icons';

function hoursLeft(expiresAt: string): number {
    return Math.max(
        0,
        Math.round((new Date(expiresAt).getTime() - Date.now()) / 3_600_000),
    );
}

export function SandboxBanner() {
    const { sandbox } = usePage().props;

    if (!sandbox) {
        return null;
    }

    return (
        <div className="flex items-center justify-center gap-2 bg-slate-900 px-4 py-2 text-center text-xs text-slate-200">
            <ClockIcon className="size-3.5 shrink-0" />
            <span>
                You're exploring a private demo sandbox with sample data.
                {sandbox.expiresAt && (
                    <>
                        {' '}
                        It resets in about{' '}
                        <span suppressHydrationWarning>
                            {hoursLeft(sandbox.expiresAt)}
                        </span>{' '}
                        hours.
                    </>
                )}
            </span>
        </div>
    );
}
