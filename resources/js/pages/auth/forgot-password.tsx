import { Form, Link } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { AuthHeading } from '@/components/auth-heading';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <AppHead title="Reset password" />
            <AuthHeading
                title="Forgot your password?"
                description="Enter your email and we'll send you a link to choose a new one."
            />

            {status && (
                <Alert tone="success" className="mb-5">
                    {status}
                </Alert>
            )}

            <Form {...email.form()} className="space-y-5">
                {({ errors, processing }) => (
                    <>
                        <FormField
                            label="Email"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                aria-invalid={!!errors.email}
                            />
                        </FormField>
                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                        >
                            Email me a reset link
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-6 text-center text-sm text-slate-500">
                <Link
                    href={login()}
                    className="font-medium text-brand hover:underline"
                >
                    Back to sign in
                </Link>
            </p>
        </>
    );
}
