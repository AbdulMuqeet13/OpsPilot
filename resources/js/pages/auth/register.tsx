import { Form, Link } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { AuthHeading } from '@/components/auth-heading';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    return (
        <>
            <AppHead title="Create a workspace" />
            <AuthHeading
                title="Create your workspace"
                description="You'll be the admin. You can invite agents afterwards."
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="space-y-5"
            >
                {({ errors, processing }) => (
                    <>
                        <FormField
                            label="Your name"
                            htmlFor="name"
                            error={errors.name}
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                autoFocus
                                autoComplete="name"
                                aria-invalid={!!errors.name}
                            />
                        </FormField>
                        <FormField
                            label="Company name"
                            htmlFor="company_name"
                            error={errors.company_name}
                        >
                            <Input
                                id="company_name"
                                name="company_name"
                                required
                                autoComplete="organization"
                                aria-invalid={!!errors.company_name}
                            />
                        </FormField>
                        <FormField
                            label="Work email"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                                aria-invalid={!!errors.email}
                            />
                        </FormField>
                        <FormField
                            label="Password"
                            htmlFor="password"
                            error={errors.password}
                        >
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                aria-invalid={!!errors.password}
                            />
                        </FormField>
                        <FormField
                            label="Confirm password"
                            htmlFor="password_confirmation"
                            error={errors.password_confirmation}
                        >
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </FormField>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                        >
                            Create workspace
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-6 text-center text-sm text-slate-500">
                Already have an account?{' '}
                <Link
                    href={login()}
                    className="font-medium text-brand hover:underline"
                >
                    Sign in
                </Link>
            </p>
        </>
    );
}
