import { Link, router, usePage } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import type { IconProps } from '@/components/icons';
import {
    ActivityIcon,
    AlertIcon,
    ChartIcon,
    HomeIcon,
    InboxIcon,
    MenuIcon,
    SettingsIcon,
    UsersIcon,
    XIcon,
} from '@/components/icons';
import { SandboxBanner } from '@/components/sandbox-banner';
import { Toaster } from '@/components/toaster';
import { UserMenu } from '@/components/user-menu';
import { brandStyle } from '@/lib/brand';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as aiActivityIndex } from '@/routes/ai-activity';
import { index as insightsIndex } from '@/routes/insights';
import { index as leadsIndex } from '@/routes/leads';
import { edit as editProfile } from '@/routes/profile';
import { index as ticketsIndex } from '@/routes/tickets';

type NavItem = {
    label: string;
    icon: ComponentType<IconProps>;
    href: string;
    matches: string;
};

function navigation(): NavItem[] {
    return [
        {
            label: 'Dashboard',
            icon: HomeIcon,
            href: dashboard.url(),
            matches: '/dashboard',
        },
        {
            label: 'Tickets',
            icon: InboxIcon,
            href: ticketsIndex.url(),
            matches: '/tickets',
        },
        {
            label: 'Leads',
            icon: UsersIcon,
            href: leadsIndex.url(),
            matches: '/leads',
        },
        {
            label: 'Insights',
            icon: ChartIcon,
            href: insightsIndex.url(),
            matches: '/insights',
        },
        {
            label: 'AI Activity',
            icon: ActivityIcon,
            href: aiActivityIndex.url(),
            matches: '/ai-activity',
        },
        {
            label: 'Settings',
            icon: SettingsIcon,
            href: editProfile.url(),
            matches: '/settings',
        },
    ];
}

function SidebarNav({ currentUrl }: { currentUrl: string }) {
    return (
        <nav className="flex flex-1 flex-col gap-1 px-3 py-4">
            {navigation().map((item) => {
                const isActive = currentUrl.startsWith(item.matches);

                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        prefetch
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-brand-50 text-brand-800'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                        )}
                    >
                        <item.icon
                            className={cn(
                                'size-5',
                                isActive ? 'text-brand' : 'text-slate-400',
                            )}
                        />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
}

function SidebarFooter() {
    return (
        <div className="border-t border-slate-100 px-5 py-4 text-xs text-slate-400">
            AI suggests, your team decides.
        </div>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { brand } = page.props;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    useEffect(() => {
        return router.on('navigate', () => setMobileNavOpen(false));
    }, []);

    return (
        <div style={brandStyle(brand)} className="min-h-screen">
            <SandboxBanner />
            {page.props.aiPaused && (
                <div
                    role="status"
                    className="flex items-center justify-center gap-2 bg-amber-50 px-4 py-2 text-center text-xs font-medium text-amber-900 ring-1 ring-amber-200"
                >
                    <AlertIcon className="size-3.5 shrink-0" />
                    {page.props.aiPaused}
                </div>
            )}

            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex">
                <div className="flex h-16 items-center border-b border-slate-100 px-5">
                    <Link href={dashboard()} className="min-w-0">
                        <BrandLogo brand={brand} />
                    </Link>
                </div>
                <SidebarNav currentUrl={page.url} />
                <SidebarFooter />
            </aside>

            {/* Mobile drawer */}
            {mobileNavOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        aria-label="Close navigation"
                        className="absolute inset-0 bg-slate-900/40"
                        onClick={() => setMobileNavOpen(false)}
                    />
                    <aside className="relative flex h-full w-72 max-w-[85%] flex-col bg-white shadow-xl">
                        <div className="flex h-16 items-center justify-between border-b border-slate-100 px-5">
                            <BrandLogo brand={brand} />
                            <button
                                type="button"
                                onClick={() => setMobileNavOpen(false)}
                                className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                            >
                                <span className="sr-only">
                                    Close navigation
                                </span>
                                <XIcon />
                            </button>
                        </div>
                        <SidebarNav currentUrl={page.url} />
                        <SidebarFooter />
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMobileNavOpen(true)}
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                    >
                        <span className="sr-only">Open navigation</span>
                        <MenuIcon />
                    </button>
                    <div className="lg:hidden">
                        <BrandLogo brand={brand} nameClassName="text-sm" />
                    </div>
                    <div className="ml-auto">
                        <UserMenu />
                    </div>
                </header>

                <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {children}
                </main>
            </div>

            <Toaster />
        </div>
    );
}
