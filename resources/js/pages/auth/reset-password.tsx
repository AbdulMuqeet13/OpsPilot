import { Form } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { AuthHeading } from '@/components/auth-heading';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/password';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    return (
        <>
            <AppHead title="Choose a new password" />
            <AuthHeading
                title="Choose a new password"
                description="Pick something you haven't used here before."
            />

            <Form
                {...store.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
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
                                value={email}
                                readOnly
                                disabled
                            />
                        </FormField>
                        <FormField
                            label="New password"
                            htmlFor="password"
                            error={errors.password}
                        >
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                aria-invalid={!!errors.password}
                            />
                        </FormField>
                        <FormField
                            label="Confirm new password"
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
                            Reset password
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
