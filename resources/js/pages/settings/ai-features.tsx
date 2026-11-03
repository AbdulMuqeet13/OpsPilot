import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { AiConnectionTest } from '@/components/ai-connection-test';
import { AppHead } from '@/components/app-head';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/ai-features';

type FeatureSetting = {
    key: string;
    label: string;
    description: string;
    is_enabled: boolean;
    supports_auto_apply: boolean;
    auto_apply: boolean;
    custom_instructions: string | null;
};

type ProviderInfo = {
    name: string;
    model: string | null;
    hasApiKey: boolean;
    embeddingProvider: string | null;
    embeddingModel: string | null;
    embeddingsEnabled: boolean;
};

type FeatureForm = {
    features: Record<
        string,
        {
            is_enabled: boolean;
            auto_apply: boolean;
            custom_instructions: string;
        }
    >;
};

function ProviderRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2.5">
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="text-right text-sm font-medium text-slate-900">
                {value}
            </dd>
        </div>
    );
}

export default function AiFeatures({
    features,
    provider,
}: {
    features: FeatureSetting[];
    provider: ProviderInfo;
}) {
    const form = useForm<FeatureForm>({
        features: Object.fromEntries(
            features.map((feature) => [
                feature.key,
                {
                    is_enabled: feature.is_enabled,
                    auto_apply: feature.auto_apply,
                    custom_instructions: feature.custom_instructions ?? '',
                },
            ]),
        ),
    });

    function setFeature(
        key: string,
        values: Partial<FeatureForm['features'][string]>,
    ) {
        form.setData('features', {
            ...form.data.features,
            [key]: { ...form.data.features[key], ...values },
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(update(), { preserveScroll: true });
    }

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <>
            <AppHead title="AI features" />

            <Card>
                <CardHeader
                    title="AI provider"
                    description="Set in the server's .env file, so a developer can switch providers without code changes."
                />
                <CardBody className="grid gap-6 lg:grid-cols-2">
                    <dl className="divide-y divide-slate-100">
                        <ProviderRow
                            label="Provider"
                            value={
                                <span className="capitalize">
                                    {provider.name}
                                </span>
                            }
                        />
                        <ProviderRow
                            label="Model"
                            value={
                                provider.model ? (
                                    <code className="font-mono text-xs">
                                        {provider.model}
                                    </code>
                                ) : (
                                    <Badge tone="warning">Not set</Badge>
                                )
                            }
                        />
                        <ProviderRow
                            label="API key"
                            value={
                                provider.hasApiKey ? (
                                    <Badge tone="success">Configured</Badge>
                                ) : (
                                    <Badge tone="warning">Missing</Badge>
                                )
                            }
                        />
                        <ProviderRow
                            label="Embeddings"
                            value={
                                provider.embeddingsEnabled ? (
                                    <span>
                                        <span className="capitalize">
                                            {provider.embeddingProvider}
                                        </span>{' '}
                                        <code className="font-mono text-xs text-slate-500">
                                            {provider.embeddingModel}
                                        </code>
                                    </span>
                                ) : (
                                    <Badge tone="neutral">
                                        Off: all snippets are sent
                                    </Badge>
                                )
                            }
                        />
                    </dl>
                    <div className="space-y-3">
                        {!provider.hasApiKey && (
                            <Alert
                                tone="warning"
                                title="AI features are waiting for an API key"
                            >
                                Add the key for{' '}
                                <span className="font-medium capitalize">
                                    {provider.name}
                                </span>{' '}
                                to .env. Until then, AI suggestions will show a
                                friendly “not set up” message.
                            </Alert>
                        )}
                        <AiConnectionTest />
                    </div>
                </CardBody>
            </Card>

            <form onSubmit={submit}>
                <Card>
                    <CardHeader
                        title="AI features"
                        description="Switch features on or off and tell the AI how your business works. Every suggestion still needs a person to accept it."
                    />
                    <ul className="divide-y divide-slate-100">
                        {features.map((feature) => {
                            const values = form.data.features[feature.key];
                            const instructionsError =
                                errors[
                                    `features.${feature.key}.custom_instructions`
                                ];

                            return (
                                <li
                                    key={feature.key}
                                    className="px-5 py-5 sm:px-6"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">
                                                {feature.label}
                                            </p>
                                            <p className="mt-0.5 text-sm text-slate-500">
                                                {feature.description}
                                            </p>
                                        </div>
                                        <Switch
                                            checked={values.is_enabled}
                                            onChange={(checked) =>
                                                setFeature(feature.key, {
                                                    is_enabled: checked,
                                                })
                                            }
                                            label={`${feature.label} enabled`}
                                        />
                                    </div>
                                    {values.is_enabled && (
                                        <div className="mt-4">
                                            <Label
                                                htmlFor={`instructions-${feature.key}`}
                                                className="mb-1.5 text-xs text-slate-600"
                                            >
                                                Custom instructions{' '}
                                                <span className="font-normal text-slate-400">
                                                    (optional)
                                                </span>
                                            </Label>
                                            <Textarea
                                                id={`instructions-${feature.key}`}
                                                rows={2}
                                                maxLength={1000}
                                                value={
                                                    values.custom_instructions
                                                }
                                                onChange={(event) =>
                                                    setFeature(feature.key, {
                                                        custom_instructions:
                                                            event.target.value,
                                                    })
                                                }
                                                placeholder="For example: Always sign off as “The Support Team”. Never promise refunds over $500."
                                                aria-invalid={
                                                    !!instructionsError
                                                }
                                            />
                                            <FieldError
                                                message={instructionsError}
                                            />
                                            {feature.supports_auto_apply && (
                                                <div className="mt-4 flex items-start justify-between gap-4 rounded-lg bg-amber-50/60 p-3 ring-1 ring-amber-200">
                                                    <div>
                                                        <p className="text-sm font-medium text-slate-900">
                                                            Apply suggestions
                                                            automatically
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-slate-600">
                                                            Skips the review
                                                            step for triage
                                                            fields only. Replies
                                                            are never sent
                                                            automatically, and
                                                            agents can still
                                                            change anything by
                                                            hand.
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        checked={
                                                            values.auto_apply
                                                        }
                                                        onChange={(checked) =>
                                                            setFeature(
                                                                feature.key,
                                                                {
                                                                    auto_apply:
                                                                        checked,
                                                                },
                                                            )
                                                        }
                                                        label={`Auto-apply ${feature.label.toLowerCase()}`}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <CardFooter>
                        <Button
                            type="submit"
                            loading={form.processing}
                            disabled={!form.isDirty}
                        >
                            Save AI settings
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </>
    );
}
