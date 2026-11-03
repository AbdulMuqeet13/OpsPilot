import { cn } from '@/lib/utils';

export function Switch({
    checked,
    onChange,
    label,
    id,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    id?: string;
}) {
    return (
        <button
            id={id}
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            onClick={() => onChange(!checked)}
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                checked ? 'bg-brand' : 'bg-slate-200',
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'pointer-events-none inline-block size-5 rounded-full bg-white shadow ring-0 transition-transform',
                    checked ? 'translate-x-5' : 'translate-x-0',
                )}
            />
        </button>
    );
}
