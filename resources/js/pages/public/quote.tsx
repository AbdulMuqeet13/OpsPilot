import { Form, usePage } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { BrandLogo } from '@/components/brand-logo';
import { CheckCircleIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { brandStyle } from '@/lib/brand';
import { store } from '@/routes/public/leads';

export default function Quote({
    workspaceSlug,
    companySizes,
    budgets,
}: {
    workspaceSlug: string;
    companySizes: string[];
    budgets: string[];
}) {
    const page = usePage();
    const { brand } = page.props;

    return (
        <div
            style={brandStyle(brand)}
            className="flex min-h-screen flex-col items-center bg-gradient-to-b from-brand-50 to-slate-50 px-4 py-12"
        >
            <AppHead title="Request a quote" />
            <BrandLogo brand={brand} nameClassName="text-lg" />

            <div className="mt-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                {page.flash.leadSubmitted ? (
                    <div className="py-6 text-center" role="status">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <CheckCircleIcon className="size-6" />
                        </div>
                        <h1 className="mt-4 text-xl font-semibold text-slate-900">
                            Thanks, we'll be in touch
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Someone from the {brand.name} team will email you
                            shortly.
                        </p>
                    </div>
                ) : (
                    <>
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">
                            Work with {brand.name}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Tell us about your project and we'll put together a
                            quote.
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
                                            />
                                        </FormField>
                                        <FormField
                                            label="Work email"
                                            htmlFor="email"
                                            error={errors.email}
                                        >
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                                autoComplete="email"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Company"
                                            htmlFor="company"
                                            error={errors.company}
                                        >
                                            <Input
                                                id="company"
                                                name="company"
                                                required
                                                autoComplete="organization"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Job title"
                                            htmlFor="job_title"
                                            error={errors.job_title}
                                        >
                                            <Input
                                                id="job_title"
                                                name="job_title"
                                                autoComplete="organization-title"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Company size"
                                            htmlFor="company_size"
                                            error={errors.company_size}
                                        >
                                            <Select
                                                id="company_size"
                                                name="company_size"
                                                defaultValue=""
                                            >
                                                <option value="">
                                                    Select…
                                                </option>
                                                {companySizes.map((size) => (
                                                    <option
                                                        key={size}
                                                        value={size}
                                                    >
                                                        {size} people
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                        <FormField
                                            label="Budget"
                                            htmlFor="budget"
                                            error={errors.budget}
                                        >
                                            <Select
                                                id="budget"
                                                name="budget"
                                                defaultValue=""
                                            >
                                                <option value="">
                                                    Select…
                                                </option>
                                                {budgets.map((budget) => (
                                                    <option
                                                        key={budget}
                                                        value={budget}
                                                    >
                                                        {budget}
                                                    </option>
                                                ))}
                                            </Select>
                                        </FormField>
                                    </div>
                                    <FormField
                                        label="Website"
                                        htmlFor="website"
                                        error={errors.website}
                                    >
                                        <Input
                                            id="website"
                                            name="website"
                                            type="url"
                                            placeholder="https://"
                                        />
                                    </FormField>
                                    <FormField
                                        label="About your project"
                                        htmlFor="message"
                                        error={errors.message}
                                    >
                                        <Textarea
                                            id="message"
                                            name="message"
                                            rows={5}
                                            required
                                            maxLength={2000}
                                            placeholder="What are you furnishing, and when do you need it?"
                                        />
                                    </FormField>
                                    {/* Honeypot: hidden from people, filled in by bots. */}
                                    <div
                                        aria-hidden="true"
                                        className="absolute -left-[9999px] h-0 w-0 overflow-hidden"
                                    >
                                        <label htmlFor="fax_number">
                                            Fax number
                                        </label>
                                        <input
                                            id="fax_number"
                                            name="fax_number"
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
                                        Request a quote
                                    </Button>
                                </>
                            )}
                        </Form>
                    </>
                )}
            </div>
        </div>
    );
}
