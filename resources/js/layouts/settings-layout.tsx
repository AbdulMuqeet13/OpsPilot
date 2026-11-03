import { Link, usePage } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import type { IconProps } from '@/components/icons';
import {
    ActivityIcon,
    InboxIcon,
    PaletteIcon,
    ShieldIcon,
    SparklesIcon,
    UserIcon,
    UsersIcon,
} from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { cn } from '@/lib/utils';
import { edit as editAiFeatures } from '@/routes/ai-features';
import { index as aiUsage } from '@/routes/ai-usage';
import { edit as editApiKey } from '@/routes/api-key';
import { edit as editBranding } from '@/routes/branding';
import { index as knowledge } from '@/routes/knowledge';
import { edit as editLeadProfile } from '@/routes/lead-profile';
import { index as ticketCategories } from '@/routes/ticket-categories';
import { edit as editProfile } from '@/routes/profile';

type SettingsLink = {
    label: string;
    href: string;
    icon: ComponentType<IconProps>;
    adminOnly: boolean;
};

export default function SettingsLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { auth } = page.props;

    const links: SettingsLink[] = [
        {
            label: 'Profile',
            href: editProfile.url(),
            icon: UserIcon,
            adminOnly: false,
        },
        {
            label: 'Branding',
            href: editBranding.url(),
            icon: PaletteIcon,
            adminOnly: true,
        },
        {
            label: 'AI features',
            href: editAiFeatures.url(),
            icon: SparklesIcon,
            adminOnly: true,
        },
        {
            label: 'AI usage',
            href: aiUsage.url(),
            icon: ActivityIcon,
            adminOnly: true,
        },
        {
            label: 'Ticket categories',
            href: ticketCategories.url(),
            icon: InboxIcon,
            adminOnly: true,
        },
        {
            label: 'Knowledge snippets',
            href: knowledge.url(),
            icon: SparklesIcon,
            adminOnly: true,
        },
        {
            label: 'Lead scoring',
            href: editLeadProfile.url(),
            icon: UsersIcon,
            adminOnly: true,
        },
        {
            label: 'API & channels',
            href: editApiKey.url(),
            icon: ShieldIcon,
            adminOnly: true,
        },
    ];

    return (
        <>
            <PageHeader
                title="Settings"
                description="Manage your account and how this workspace looks and behaves."
            />
            <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                <nav className="-mx-4 flex gap-1 overflow-x-auto px-4 lg:mx-0 lg:w-56 lg:shrink-0 lg:flex-col lg:px-0">
                    {links
                        .filter(
                            (link) =>
                                !link.adminOnly || auth.can.manageSettings,
                        )
                        .map((link) => {
                            const path = page.url.split('?')[0];
                            const isActive = path === link.href;

                            return (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    prefetch
                                    className={cn(
                                        'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium',
                                        isActive
                                            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                            : 'text-slate-600 hover:bg-white/70 hover:text-slate-900',
                                    )}
                                >
                                    <link.icon
                                        className={cn(
                                            'size-4',
                                            isActive
                                                ? 'text-brand'
                                                : 'text-slate-400',
                                        )}
                                    />
                                    {link.label}
                                </Link>
                            );
                        })}
                </nav>
                <div className="min-w-0 flex-1 space-y-6">{children}</div>
            </div>
        </>
    );
}
