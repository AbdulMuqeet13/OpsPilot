import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { SparklesIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/knowledge';

type Snippet = {
    id: number;
    title: string;
    content: string;
    isEmbedded: boolean;
    updatedAt: string | null;
};

function SnippetForm({
    snippet,
    onDone,
}: {
    snippet?: Snippet;
    onDone?: () => void;
}) {
    const key = snippet?.id ?? 'new';

    return (
        <Form
            {...(snippet ? update.form(snippet.id) : store.form())}
            options={{ preserveScroll: true }}
            resetOnSuccess={!snippet}
            onSuccess={onDone}
            className="space-y-3"
        >
            {({ errors, processing }) => (
                <>
                    <FormField
                        label="Title"
                        htmlFor={`title-${key}`}
                        error={errors.title}
                    >
                        <Input
                            id={`title-${key}`}
                            name="title"
                            defaultValue={snippet?.title}
                            maxLength={120}
                            required
                            placeholder="e.g. Returns within 30 days"
                        />
                    </FormField>
                    <FormField
                        label="Policy or answer"
                        htmlFor={`content-${key}`}
                        error={errors.content}
                        hint="Write it the way you'd explain it to a new agent. Up to 2,000 characters."
                    >
                        <Textarea
                            id={`content-${key}`}
                            name="content"
                            rows={4}
                            defaultValue={snippet?.content}
                            maxLength={2000}
                            required
                        />
                    </FormField>
                    <div className="flex justify-end gap-2">
                        {onDone && snippet && (
                            <Button variant="ghost" onClick={onDone}>
                                Cancel
                            </Button>
                        )}
                        <Button type="submit" loading={processing}>
                            {snippet ? 'Save snippet' : 'Add snippet'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function Knowledge({
    snippets,
    maxSnippets,
}: {
    snippets: Snippet[];
    maxSnippets: number;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <>
            <AppHead title="Knowledge snippets" />
            <Card>
                <CardHeader
                    title="Knowledge snippets"
                    description="Your policies and FAQ answers. AI reply drafts may only state facts that appear here, so keep them accurate."
                />
                {snippets.length === 0 ? (
                    <EmptyState
                        icon={SparklesIcon}
                        title="No snippets yet"
                        description="Without snippets, drafts stay generic and promise to follow up. Add your returns, delivery and warranty policies to get specific answers."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {snippets.map((snippet) => (
                            <li key={snippet.id} className="px-5 py-4 sm:px-6">
                                {editingId === snippet.id ? (
                                    <SnippetForm
                                        snippet={snippet}
                                        onDone={() => setEditingId(null)}
                                    />
                                ) : (
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                                {snippet.title}
                                                {snippet.isEmbedded && (
                                                    <Badge tone="brand">
                                                        Indexed for search
                                                    </Badge>
                                                )}
                                            </p>
                                            <p className="mt-1 text-sm whitespace-pre-line text-slate-600">
                                                {snippet.content}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setEditingId(snippet.id)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Delete “${snippet.title}”?`,
                                                    ) &&
                                                    router.delete(
                                                        destroy.url(snippet.id),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <CardBody className="border-t border-slate-100 bg-slate-50/60">
                    {snippets.length < maxSnippets ? (
                        <SnippetForm />
                    ) : (
                        <p className="text-sm text-slate-500">
                            You've reached the limit of {maxSnippets} snippets.
                        </p>
                    )}
                </CardBody>
            </Card>
        </>
    );
}
