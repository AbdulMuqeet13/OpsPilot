import { Form } from '@inertiajs/react';
import { AppHead } from '@/components/app-head';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/lead-profile';

export default function LeadScoring({
    profile,
    leadFormUrl,
}: {
    profile: {
        ideal_customer_profile: string | null;
        offer_summary: string | null;
    };
    leadFormUrl: string;
}) {
    return (
        <>
            <AppHead title="Lead scoring" />
            <Form {...update.form()} options={{ preserveScroll: true }}>
                {({ errors, processing, isDirty }) => (
                    <Card>
                        <CardHeader
                            title="Lead scoring"
                            description="Describe your best customers in plain English. The AI scores every lead against this and explains its reasoning."
                        />
                        <CardBody className="space-y-5">
                            <FormField
                                label="Ideal customer"
                                htmlFor="ideal_customer_profile"
                                error={errors.ideal_customer_profile}
                                hint="Who buys from you, how big they are, what they need and what makes a lead not worth chasing."
                            >
                                <Textarea
                                    id="ideal_customer_profile"
                                    name="ideal_customer_profile"
                                    rows={7}
                                    maxLength={2000}
                                    defaultValue={
                                        profile.ideal_customer_profile ?? ''
                                    }
                                    placeholder="e.g. Interior designers, boutique hotels and offices furnishing 10+ rooms or 50+ desks, with a budget above $20k and a project in the next 6 months. Not a fit: one-off home purchases, students, resellers."
                                />
                            </FormField>
                            <FormField
                                label="What you offer"
                                htmlFor="offer_summary"
                                error={errors.offer_summary}
                                hint="Used in follow-up emails. Only include things you're happy for the AI to mention."
                            >
                                <Textarea
                                    id="offer_summary"
                                    name="offer_summary"
                                    rows={4}
                                    maxLength={1000}
                                    defaultValue={profile.offer_summary ?? ''}
                                    placeholder="e.g. Trade programme with volume pricing from 10 pieces, a dedicated account manager, and white-glove delivery and installation."
                                />
                            </FormField>
                            <p className="text-xs text-slate-500">
                                Your public lead form:{' '}
                                <a
                                    href={leadFormUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="font-medium text-brand hover:underline"
                                >
                                    {leadFormUrl}
                                </a>
                            </p>
                        </CardBody>
                        <CardFooter>
                            <Button
                                type="submit"
                                loading={processing}
                                disabled={!isDirty}
                            >
                                Save
                            </Button>
                        </CardFooter>
                    </Card>
                )}
            </Form>
        </>
    );
}
