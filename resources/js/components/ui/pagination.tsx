import { Link } from '@inertiajs/react';
import { buttonClasses } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export function Pagination({
    paginator,
    only,
}: {
    paginator: Paginated<unknown>;
    only?: string[];
}) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const linkClasses = (enabled: boolean) =>
        cn(buttonClasses('secondary', 'sm'), !enabled && 'pointer-events-none opacity-50');

    return (
        <nav
            aria-label="Pagination"
            className="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm text-slate-500 sm:px-6"
        >
            <p>
                Showing <span className="font-medium text-slate-700">{paginator.from}</span>–
                <span className="font-medium text-slate-700">{paginator.to}</span> of{' '}
                <span className="font-medium text-slate-700">{paginator.total}</span>
            </p>
            <div className="flex gap-2">
                <Link
                    href={paginator.prev_page_url ?? '#'}
                    only={only}
                    preserveScroll
                    className={linkClasses(paginator.prev_page_url !== null)}
                    aria-disabled={paginator.prev_page_url === null}
                >
                    Previous
                </Link>
                <Link
                    href={paginator.next_page_url ?? '#'}
                    only={only}
                    preserveScroll
                    className={linkClasses(paginator.next_page_url !== null)}
                    aria-disabled={paginator.next_page_url === null}
                >
                    Next
                </Link>
            </div>
        </nav>
    );
}
