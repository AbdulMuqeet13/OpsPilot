import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index as dailyBriefs } from '@/routes/daily-briefs';
import { index as insights } from '@/routes/insights';

export function InsightsTabs() {
    const path = usePage().url.split('?')[0];
    const tabs = [
        { label: 'Charts', href: insights.url() },
        { label: 'Daily brief', href: dailyBriefs.url() },
    ];

    return (
        <nav
            className="mb-6 flex gap-1 border-b border-slate-200"
            aria-label="Insights"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.href}
                    href={tab.href}
                    prefetch
                    aria-current={path === tab.href ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2.5 text-sm font-medium',
                        path === tab.href
                            ? 'border-brand text-brand-800'
                            : 'border-transparent text-slate-500 hover:text-slate-800',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
