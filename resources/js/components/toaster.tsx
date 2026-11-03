import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    AlertIcon,
    CheckCircleIcon,
    InfoIcon,
    XIcon,
} from '@/components/icons';
import { cn } from '@/lib/utils';
import type { Toast } from '@/types';

type QueuedToast = Toast & { id: number };

let nextToastId = 1;

const toneStyles = {
    success: { Icon: CheckCircleIcon, iconClass: 'text-emerald-500' },
    error: { Icon: AlertIcon, iconClass: 'text-rose-500' },
    info: { Icon: InfoIcon, iconClass: 'text-sky-500' },
} as const;

/**
 * Shows toasts flashed from the server with Inertia::flash('toast', [...]).
 */
export function Toaster() {
    const initialToast = usePage().flash.toast;
    const [toasts, setToasts] = useState<QueuedToast[]>(() =>
        initialToast ? [{ ...initialToast, id: nextToastId++ }] : [],
    );

    useEffect(() => {
        return router.on('flash', (event) => {
            const toast = event.detail.flash.toast;

            if (toast) {
                setToasts((current) => [
                    ...current,
                    { ...toast, id: nextToastId++ },
                ]);
            }
        });
    }, []);

    useEffect(() => {
        if (toasts.length === 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            setToasts((current) => current.slice(1));
        }, 5000);

        return () => window.clearTimeout(timer);
    }, [toasts]);

    function dismiss(id: number) {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }

    return (
        <div
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
        >
            {toasts.map((toast) => {
                const { Icon, iconClass } = toneStyles[toast.type];

                return (
                    <div
                        key={toast.id}
                        className="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-200 transition starting:translate-y-2 starting:opacity-0"
                    >
                        <Icon
                            className={cn('mt-0.5 size-5 shrink-0', iconClass)}
                        />
                        <p className="flex-1 text-sm text-slate-700">
                            {toast.message}
                        </p>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            className="rounded text-slate-400 hover:text-slate-600"
                        >
                            <span className="sr-only">Dismiss</span>
                            <XIcon className="size-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
