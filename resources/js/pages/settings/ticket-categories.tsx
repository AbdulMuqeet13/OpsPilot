import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { InboxIcon } from '@/components/icons';
import { CategoryBadge, categorySwatches } from '@/components/ticket-badges';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { FieldError } from '@/components/ui/field-error';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, store, update } from '@/routes/ticket-categories';

type Category = {
    id: number;
    name: string;
    color: string;
    description: string | null;
    ticketsCount: number;
};

function ColorPicker({
    colors,
    value,
    onChange,
}: {
    colors: string[];
    value: string;
    onChange: (color: string) => void;
}) {
    return (
        <div
            className="flex flex-wrap gap-1.5"
            role="radiogroup"
            aria-label="Color"
        >
            {colors.map((color) => (
                <button
                    key={color}
                    type="button"
                    role="radio"
                    aria-checked={value === color}
                    aria-label={color}
                    onClick={() => onChange(color)}
                    className={cn(
                        'size-6 rounded-full ring-offset-2 transition',
                        categorySwatches[color],
                        value === color
                            ? 'ring-2 ring-slate-900'
                            : 'hover:scale-110',
                    )}
                />
            ))}
        </div>
    );
}

function CategoryForm({
    colors,
    category,
    onDone,
}: {
    colors: string[];
    category?: Category;
    onDone?: () => void;
}) {
    const [color, setColor] = useState(category?.color ?? colors[0]);
    const route = category ? update.form(category.id) : store.form();

    return (
        <Form
            {...route}
            options={{ preserveScroll: true }}
            transform={(data) => ({ ...data, color })}
            resetOnSuccess={!category}
            onSuccess={onDone}
            className="grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto_auto] sm:items-start"
        >
            {({ errors, processing }) => (
                <>
                    <div>
                        <label
                            htmlFor={`name-${category?.id ?? 'new'}`}
                            className="sr-only"
                        >
                            Name
                        </label>
                        <Input
                            id={`name-${category?.id ?? 'new'}`}
                            name="name"
                            defaultValue={category?.name}
                            placeholder="Name, e.g. Refunds"
                            maxLength={60}
                            required
                            aria-invalid={!!errors.name}
                        />
                        <FieldError message={errors.name} />
                    </div>
                    <div>
                        <label
                            htmlFor={`description-${category?.id ?? 'new'}`}
                            className="sr-only"
                        >
                            Description
                        </label>
                        <Input
                            id={`description-${category?.id ?? 'new'}`}
                            name="description"
                            defaultValue={category?.description ?? ''}
                            placeholder="When should the AI pick this? e.g. customer wants their money back"
                            maxLength={255}
                        />
                        <FieldError message={errors.description} />
                    </div>
                    <div className="pt-2">
                        <ColorPicker
                            colors={colors}
                            value={color}
                            onChange={setColor}
                        />
                    </div>
                    <div className="flex gap-2">
                        {onDone && category && (
                            <Button variant="ghost" onClick={onDone}>
                                Cancel
                            </Button>
                        )}
                        <Button type="submit" loading={processing}>
                            {category ? 'Save' : 'Add category'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function TicketCategories({
    categories,
    colors,
    maxCategories,
}: {
    categories: Category[];
    colors: string[];
    maxCategories: number;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    function remove(category: Category) {
        const message =
            category.ticketsCount > 0
                ? `Remove “${category.name}”? Its ${category.ticketsCount} tickets will become uncategorised.`
                : `Remove “${category.name}”?`;

        if (window.confirm(message)) {
            router.delete(destroy.url(category.id), { preserveScroll: true });
        }
    }

    return (
        <>
            <AppHead title="Ticket categories" />
            <Card>
                <CardHeader
                    title="Ticket categories"
                    description="The AI must choose one of these for every ticket. Descriptions help it pick the right one."
                />
                {categories.length === 0 ? (
                    <EmptyState
                        icon={InboxIcon}
                        title="No categories yet"
                        description="Add a few, such as Delivery delays, Refunds or Damaged items, and new tickets will be sorted into them."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {categories.map((category) => (
                            <li key={category.id} className="px-5 py-4 sm:px-6">
                                {editingId === category.id ? (
                                    <CategoryForm
                                        colors={colors}
                                        category={category}
                                        onDone={() => setEditingId(null)}
                                    />
                                ) : (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <CategoryBadge
                                                category={category}
                                            />
                                            <p className="mt-1 text-sm text-slate-500">
                                                {category.description ||
                                                    'No description'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-slate-400 tabular-nums">
                                                {category.ticketsCount} tickets
                                            </span>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setEditingId(category.id)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => remove(category)}
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <CardBody className="border-t border-slate-100 bg-slate-50/60">
                    {categories.length < maxCategories ? (
                        <CategoryForm colors={colors} />
                    ) : (
                        <p className="text-sm text-slate-500">
                            You've reached the limit of {maxCategories}{' '}
                            categories.
                        </p>
                    )}
                </CardBody>
            </Card>
        </>
    );
}
