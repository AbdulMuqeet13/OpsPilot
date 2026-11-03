import { Form, Link } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { AuthHeading } from '@/components/auth-heading';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';

export default function Login({
    status,
    canRegister,
}: {
    status?: string;
    canRegister: boolean;
}) {
    return (
        <>
            <AppHead title="Sign in" />
            <AuthHeading
                title="Sign in to your workspace"
                description="Welcome back. Enter your details to continue."
            />

            {status && (
                <Alert tone="success" className="mb-5">
                    {status}
                </Alert>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="space-y-5"
            >
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

                        <FormField
                            label="Password"
                            htmlFor="password"
                            error={errors.password}
                            action={
                                <Link
                                    href={requestPasswordReset()}
                                    className="text-xs font-medium text-brand hover:underline"
                                >
                                    Forgot password?
                                </Link>
                            }
                        >
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                aria-invalid={!!errors.password}
                            />
                        </FormField>

                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <Checkbox name="remember" value="1" />
                            Keep me signed in
                        </label>

                        <Button
                            type="submit"
                            className="w-full"
                            loading={processing}
                        >
                            Sign in
                        </Button>
                    </>
                )}
            </Form>

            {canRegister && (
                <p className="mt-6 text-center text-sm text-slate-500">
                    New here?{' '}
                    <Link
                        href={register()}
                        className="font-medium text-brand hover:underline"
                    >
                        Create a workspace
                    </Link>
                </p>
            )}
        </>
    );
}
