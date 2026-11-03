import { Head, usePage } from '@inertiajs/react';

/**
 * Page <title> that always carries the current workspace's brand name.
 */
export function AppHead({ title }: { title?: string }) {
    const { brand } = usePage().props;

    return <Head title={title ? `${title} · ${brand.name}` : brand.name} />;
}
