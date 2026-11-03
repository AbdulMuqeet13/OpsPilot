import { Form, usePage } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { update as updatePassword } from '@/routes/password';
import { update as updateProfile } from '@/routes/profile';

export default function Profile({
    isSandboxAccount,
}: {
    isSandboxAccount: boolean;
}) {
    const user = usePage().props.auth.user!;

    return (
        <>
            <AppHead title="Profile" />

            <Card>
                <CardHeader
                    title="Profile"
                    description="Your name and email as your teammates see them."
                />
                <Form
                    {...updateProfile.form()}
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, isDirty }) => (
                        <>
                            <CardBody className="grid gap-5 sm:grid-cols-2">
                                <FormField
                                    label="Name"
                                    htmlFor="name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user.name}
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
                                        type="email"
                                        name="email"
                                        defaultValue={user.email}
                                        required
                                        autoComplete="email"
                                        aria-invalid={!!errors.email}
                                    />
                                </FormField>
                            </CardBody>
                            <CardFooter>
                                <Button
                                    type="submit"
                                    loading={processing}
                                    disabled={!isDirty}
                                >
                                    Save profile
                                </Button>
                            </CardFooter>
                        </>
                    )}
                </Form>
            </Card>

            <Card>
                <CardHeader
                    title="Password"
                    description="Use a long, unique password to keep your workspace safe."
                />
                {isSandboxAccount ? (
                    <CardBody>
                        <Alert tone="info">
                            Demo sandbox accounts sign in with one click, so
                            they don't have a password to change.
                        </Alert>
                    </CardBody>
                ) : (
                    <Form
                        {...updatePassword.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        resetOnError={[
                            'current_password',
                            'password',
                            'password_confirmation',
                        ]}
                    >
                        {({ errors, processing }) => (
                            <>
                                <CardBody className="grid gap-5 sm:max-w-md">
                                    <FormField
                                        label="Current password"
                                        htmlFor="current_password"
                                        error={errors.current_password}
                                    >
                                        <Input
                                            id="current_password"
                                            type="password"
                                            name="current_password"
                                            autoComplete="current-password"
                                            aria-invalid={
                                                !!errors.current_password
                                            }
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
                                            autoComplete="new-password"
                                        />
                                    </FormField>
                                </CardBody>
                                <CardFooter>
                                    <Button type="submit" loading={processing}>
                                        Update password
                                    </Button>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                )}
            </Card>
        </>
    );
}
