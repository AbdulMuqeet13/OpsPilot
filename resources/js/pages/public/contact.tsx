import { Form, usePage } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { BrandLogo } from '@/components/brand-logo';
import { CheckCircleIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { brandStyle } from '@/lib/brand';
import { store } from '@/routes/public/tickets';

export default function Contact({ workspaceSlug }: { workspaceSlug: string }) {
    const page = usePage();
    const { brand } = page.props;
    const submittedReference = page.flash.submittedReference;

    return (
        <div
            style={brandStyle(brand)}
            className="flex min-h-screen flex-col items-center bg-gradient-to-b from-brand-50 to-slate-50 px-4 py-12"
        >
            <AppHead title="Contact support" />
            <BrandLogo brand={brand} nameClassName="text-lg" />

            <div className="mt-8 w-full max-w-xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                {submittedReference ? (
                    <div className="py-6 text-center" role="status">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <CheckCircleIcon className="size-6" />
                        </div>
                        <h1 className="mt-4 text-xl font-semibold text-slate-900">
                            Thanks, we've got your message
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Your reference is{' '}
                            <span className="font-semibold text-slate-900">
                                {submittedReference}
                            </span>
                            . A member of the {brand.name} team will reply by
                            email, usually within one business day.
                        </p>
                    </div>
                ) : (
                    <>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">
                            How can we help?
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Tell us what's going on and we'll get back to you by
                            email.
                        </p>

                        <Form
                            {...store.form(workspaceSlug)}
                            resetOnSuccess
                            className="mt-6 space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <FormField
                                            label="Your name"
                                            htmlFor="name"
                                            error={errors.name}
                                        >
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoComplete="name"
                                                aria-invalid={!!errors.name}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Email"
                                            htmlFor="email"
                                            error={errors.email}
                                        >
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                                autoComplete="email"
                                                aria-invalid={!!errors.email}
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        label="Subject"
                                        htmlFor="subject"
                                        error={errors.subject}
                                    >
                                        <Input
                                            id="subject"
                                            name="subject"
                                            required
                                            maxLength={200}
                                            aria-invalid={!!errors.subject}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Message"
                                        htmlFor="message"
                                        error={errors.message}
                                        hint="Include your order number if you have one."
                                    >
                                        <Textarea
                                            id="message"
                                            name="message"
                                            rows={6}
                                            required
                                            maxLength={5000}
                                            aria-invalid={!!errors.message}
                                        />
                                    </FormField>
                                    {/* Honeypot: hidden from people, filled in by bots. */}
                                    <div
                                        aria-hidden="true"
                                        className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
                                    >
                                        <label htmlFor="website">Website</label>
                                        <input
                                            id="website"
                                            name="website"
                                            type="text"
                                            tabIndex={-1}
                                            autoComplete="off"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        loading={processing}
                                    >
                                        Send message
                                    </Button>
                                </>
                            )}
                        </Form>
                    </>
                )}
            </div>

            {brand.supportEmail && (
                <p className="mt-6 text-xs text-slate-500">
                    Prefer email? Write to{' '}
                    <a
                        href={`mailto:${brand.supportEmail}`}
                        className="font-medium text-slate-700 hover:underline"
                    >
                        {brand.supportEmail}
                    </a>
                </p>
            )}
        </div>
    );
}
