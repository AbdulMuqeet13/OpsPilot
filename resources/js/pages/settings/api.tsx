import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { RelativeTime } from '@/components/relative-time';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { destroy, store } from '@/routes/api-key';

export default function ApiSettings({
    apiKey,
    endpoint,
    contactFormUrl,
}: {
    apiKey: { lastFour: string; createdAt: string | null } | null;
    endpoint: string;
    contactFormUrl: string;
}) {
    const newKey = usePage().flash.newApiKey;
    const [copied, setCopied] = useState<string | null>(null);

    function copy(label: string, text: string) {
        void navigator.clipboard.writeText(text).then(() => {
            setCopied(label);
            window.setTimeout(() => setCopied(null), 2000);
        });
    }

    const exampleKey = newKey ?? 'ops_your_api_key';
    const curl = `curl -X POST ${endpoint} \\
  -H "Authorization: Bearer ${exampleKey}" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{"name":"Sam Lee","email":"sam@example.com","subject":"Wrong colour delivered","message":"I ordered the sage armchair but received grey."}'`;

    return (
        <>
            <AppHead title="API & channels" />

            <Card>
                <CardHeader
                    title="Public contact form"
                    description="Share this link or embed it on your website. Every submission becomes a ticket."
                />
                <CardBody className="flex flex-wrap items-center gap-3">
                    <code className="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700 ring-1 ring-slate-200">
                        {contactFormUrl}
                    </code>
                    <Button
                        variant="secondary"
                        onClick={() => copy('form', contactFormUrl)}
                    >
                        {copied === 'form' ? 'Copied' : 'Copy link'}
                    </Button>
                    <a
                        href={contactFormUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm font-medium text-brand hover:underline"
                    >
                        Open
                    </a>
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title="Tickets API"
                    description="Create tickets from your own systems: order pages, chat widgets or other inboxes."
                />
                <CardBody className="space-y-4">
                    {newKey && (
                        <Alert
                            tone="success"
                            title="Here's your new API key. Copy it now, it won't be shown again."
                        >
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <code className="rounded bg-white px-2 py-1 font-mono text-xs text-slate-900 ring-1 ring-emerald-200">
                                    {newKey}
                                </code>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => copy('key', newKey)}
                                >
                                    {copied === 'key' ? 'Copied' : 'Copy'}
                                </Button>
                            </div>
                        </Alert>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-600">
                            {apiKey ? (
                                <>
                                    Active key ending in{' '}
                                    <code className="font-mono text-slate-900">
                                        …{apiKey.lastFour}
                                    </code>
                                    , created{' '}
                                    <RelativeTime iso={apiKey.createdAt} />.
                                </>
                            ) : (
                                'No API key yet.'
                            )}
                        </p>
                        <div className="flex gap-2">
                            {apiKey && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        window.confirm(
                                            'Revoke this key? Anything using it will stop working.',
                                        ) &&
                                        router.delete(destroy.url(), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Revoke
                                </Button>
                            )}
                            <Button
                                variant={apiKey ? 'secondary' : 'primary'}
                                onClick={() =>
                                    (!apiKey ||
                                        window.confirm(
                                            'Create a new key? The current one stops working immediately.',
                                        )) &&
                                    router.post(
                                        store.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {apiKey ? 'Rotate key' : 'Create API key'}
                            </Button>
                        </div>
                    </div>

                    <div>
                        <div className="mb-1.5 flex items-center justify-between">
                            <p className="text-xs font-medium text-slate-500">
                                Example request
                            </p>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => copy('curl', curl)}
                            >
                                {copied === 'curl' ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                        <pre className="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-5 text-slate-100">
                            {curl}
                        </pre>
                        <p className="mt-2 text-xs text-slate-500">
                            Returns 201 with the ticket's reference. Invalid
                            keys get 401, invalid fields get 422.
                        </p>
                    </div>
                </CardBody>
            </Card>
        </>
    );
}
