import { Form, Link, usePage } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { AppHead } from '@/components/app-head';
import { BrandLogo } from '@/components/brand-logo';
import type { IconProps } from '@/components/icons';
import {
    ArrowRightIcon,
    ChartIcon,
    InboxIcon,
    ShieldIcon,
    SparklesIcon,
    UsersIcon,
} from '@/components/icons';
import { Alert } from '@/components/ui/alert';
import { Button, buttonClasses } from '@/components/ui/button';
import { brandStyle } from '@/lib/brand';
import { dashboard, login } from '@/routes';
import { store as startDemo } from '@/routes/demo';

type Feature = {
    icon: ComponentType<IconProps>;
    title: string;
    description: string;
};

const features: Feature[] = [
    {
        icon: InboxIcon,
        title: 'Support tickets, pre-triaged',
        description:
            'Every ticket arrives with a suggested category, priority, sentiment, language, summary and a draft reply grounded in your own policies.',
    },
    {
        icon: UsersIcon,
        title: 'Leads, scored and ready',
        description:
            'Leads are scored hot, warm or cold against your ideal customer, with a plain-English reason and a personalised first follow-up.',
    },
    {
        icon: ChartIcon,
        title: 'A daily brief that writes itself',
        description:
            "Yesterday's tickets and leads summarised, with recurring complaint themes and the urgent items your team should act on first.",
    },
    {
        icon: ShieldIcon,
        title: 'Humans stay in control',
        description:
            'Nothing is sent without approval. Every AI decision is logged with whether your team accepted, edited or rejected it.',
    },
];

export default function Welcome({ demoMode }: { demoMode: boolean }) {
    const { auth, brand, errors } = usePage().props;

    return (
        <div style={brandStyle(brand)} className="min-h-screen bg-white">
            <AppHead title="AI for support and sales teams" />

            <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <BrandLogo brand={brand} />
                {auth.user ? (
                    <Link
                        href={dashboard()}
                        className={buttonClasses('secondary', 'sm')}
                    >
                        Open dashboard
                    </Link>
                ) : (
                    <Link
                        href={login()}
                        className={buttonClasses('ghost', 'sm')}
                    >
                        Sign in
                    </Link>
                )}
            </header>

            <main>
                <section className="relative overflow-hidden">
                    <div className="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-gradient-to-b from-brand-50 to-white" />
                    <div className="mx-auto max-w-4xl px-6 pt-16 pb-20 text-center sm:pt-24">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-800 shadow-sm ring-1 ring-brand-200">
                            <SparklesIcon className="size-3.5" />
                            An AI layer for the dashboard you already know
                        </span>
                        <h1 className="mt-6 text-4xl font-semibold tracking-tight text-balance text-slate-900 sm:text-6xl">
                            Let AI do the repetitive work. Your team makes the
                            calls.
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-pretty text-slate-600">
                            {brand.name} triages support tickets, drafts
                            replies, scores leads and writes a daily brief, then
                            hands every suggestion to a person to accept, edit
                            or reject.
                        </p>

                        {demoMode && !auth.user ? (
                            <div className="mt-10">
                                <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                    <Form {...startDemo.form('admin')}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="lg"
                                                loading={processing}
                                            >
                                                Try the demo as an Admin
                                                <ArrowRightIcon className="size-4" />
                                            </Button>
                                        )}
                                    </Form>
                                    <Form {...startDemo.form('agent')}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="lg"
                                                variant="secondary"
                                                loading={processing}
                                            >
                                                Try it as a Support Agent
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                                <p className="mt-4 text-sm text-slate-500">
                                    No sign-up. You get a private copy of a
                                    sample store that resets after a day.
                                </p>
                                {errors.demo && (
                                    <Alert
                                        tone="warning"
                                        className="mx-auto mt-6 max-w-md text-left"
                                    >
                                        {errors.demo}
                                    </Alert>
                                )}
                            </div>
                        ) : (
                            <div className="mt-10 flex justify-center">
                                <Link
                                    href={auth.user ? dashboard() : login()}
                                    className={buttonClasses('primary', 'lg')}
                                >
                                    {auth.user
                                        ? 'Go to your dashboard'
                                        : 'Sign in'}
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            </div>
                        )}
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 pb-24">
                    <div className="grid gap-6 sm:grid-cols-2">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
                            >
                                <div className="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand">
                                    <feature.icon className="size-5" />
                                </div>
                                <h2 className="mt-4 text-base font-semibold text-slate-900">
                                    {feature.title}
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
            </main>

            <footer className="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
                © {new Date().getFullYear()} {brand.name}
                {brand.supportEmail && (
                    <>
                        {' · '}
                        <a
                            href={`mailto:${brand.supportEmail}`}
                            className="hover:text-slate-600"
                        >
                            {brand.supportEmail}
                        </a>
                    </>
                )}
            </footer>
        </div>
    );
}
