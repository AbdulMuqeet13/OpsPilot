import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { AppHead } from '@/components/app-head';
import { BrandLogo } from '@/components/brand-logo';
import { InboxIcon, HomeIcon, UploadIcon, UsersIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brandStyle } from '@/lib/brand';
import { update } from '@/routes/branding';

type BrandingSettings = {
    brand_name: string;
    primary_color: string;
    support_email: string | null;
};

type BrandingForm = {
    brand_name: string;
    primary_color: string;
    support_email: string;
    logo: File | null;
    remove_logo: boolean;
};

const presetColors = [
    '#4f46e5',
    '#0f766e',
    '#9a5b2e',
    '#be123c',
    '#1d4ed8',
    '#0f172a',
];

const hexColorPattern = /^#[0-9a-f]{6}$/i;

/**
 * Mirrors the server's black/white choice so the preview matches what will be saved.
 */
function foregroundFor(hex: string): string {
    const [red, green, blue] = [1, 3, 5].map((offset) => {
        const value = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue > 0.4
        ? '#0f172a'
        : '#ffffff';
}

function useObjectUrl(file: File | null): string | null {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}

export default function Branding({
    branding,
    maxLogoKilobytes,
}: {
    branding: BrandingSettings;
    maxLogoKilobytes: number;
}) {
    const { brand } = usePage().props;
    const form = useForm<BrandingForm>({
        brand_name: branding.brand_name,
        primary_color: branding.primary_color,
        support_email: branding.support_email ?? '',
        logo: null,
        remove_logo: false,
    });
    const newLogoUrl = useObjectUrl(form.data.logo);

    const previewColor = hexColorPattern.test(form.data.primary_color)
        ? form.data.primary_color
        : brand.primaryColor;
    const previewLogoUrl =
        newLogoUrl ?? (form.data.remove_logo ? null : brand.logoUrl);
    const previewBrand = {
        name: form.data.brand_name || 'Your brand',
        logoUrl: previewLogoUrl,
    };

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(update.form().action, {
            preserveScroll: true,
            onSuccess: () =>
                form.setDefaults({
                    ...form.data,
                    logo: null,
                    remove_logo: false,
                }),
        });
    }

    return (
        <>
            <AppHead title="Branding" />

            <form onSubmit={submit}>
                <Card>
                    <CardHeader
                        title="Branding"
                        description="Re-skin the whole app (dashboard, sign-in page and public forms) for any client."
                    />
                    <CardBody className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
                        <div className="space-y-6">
                            <FormField
                                label="Brand name"
                                htmlFor="brand_name"
                                error={form.errors.brand_name}
                            >
                                <Input
                                    id="brand_name"
                                    value={form.data.brand_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'brand_name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={60}
                                    required
                                    aria-invalid={!!form.errors.brand_name}
                                />
                            </FormField>

                            <div>
                                <Label htmlFor="logo" className="mb-1.5">
                                    Logo
                                </Label>
                                <div className="flex flex-wrap items-center gap-4">
                                    <BrandLogo
                                        brand={previewBrand}
                                        showName={false}
                                        size="lg"
                                    />
                                    <label
                                        htmlFor="logo"
                                        className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg bg-white px-3 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-300 ring-inset focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand hover:bg-slate-50"
                                    >
                                        <UploadIcon className="size-4 text-slate-400" />
                                        {previewLogoUrl
                                            ? 'Replace logo'
                                            : 'Upload logo'}
                                        <input
                                            id="logo"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            className="sr-only"
                                            onChange={(event) => {
                                                form.setData(
                                                    'logo',
                                                    event.target.files?.[0] ??
                                                        null,
                                                );
                                                form.setData(
                                                    'remove_logo',
                                                    false,
                                                );
                                                event.target.value = '';
                                            }}
                                        />
                                    </label>
                                    {previewLogoUrl && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                form.setData('logo', null);
                                                form.setData(
                                                    'remove_logo',
                                                    brand.logoUrl !== null,
                                                );
                                            }}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                                <p className="mt-1.5 text-xs text-slate-500">
                                    PNG, JPG or WebP, up to{' '}
                                    {Math.round(
                                        (maxLogoKilobytes / 1024) * 10,
                                    ) / 10}{' '}
                                    MB. A square image works best.
                                </p>
                                <FieldError message={form.errors.logo} />
                            </div>

                            <div>
                                <Label
                                    htmlFor="primary_color"
                                    className="mb-1.5"
                                >
                                    Primary color
                                </Label>
                                <div className="flex flex-wrap items-center gap-3">
                                    <input
                                        type="color"
                                        aria-label="Pick primary color"
                                        value={previewColor}
                                        onChange={(event) =>
                                            form.setData(
                                                'primary_color',
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 w-12 cursor-pointer rounded-lg border-0 bg-white p-1 shadow-sm ring-1 ring-slate-300"
                                    />
                                    <Input
                                        id="primary_color"
                                        value={form.data.primary_color}
                                        onChange={(event) =>
                                            form.setData(
                                                'primary_color',
                                                event.target.value,
                                            )
                                        }
                                        className="w-32 font-mono"
                                        maxLength={7}
                                        aria-invalid={
                                            !!form.errors.primary_color
                                        }
                                    />
                                    <div className="flex gap-1.5">
                                        {presetColors.map((color) => (
                                            <button
                                                key={color}
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'primary_color',
                                                        color,
                                                    )
                                                }
                                                className="size-7 rounded-full ring-2 ring-white ring-offset-1 ring-offset-slate-200 transition hover:scale-110"
                                                style={{
                                                    backgroundColor: color,
                                                }}
                                            >
                                                <span className="sr-only">
                                                    Use {color}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <FieldError
                                    message={form.errors.primary_color}
                                />
                            </div>

                            <FormField
                                label="Support email"
                                htmlFor="support_email"
                                error={form.errors.support_email}
                                hint="Shown on sign-in and public pages so customers know where to reach you."
                            >
                                <Input
                                    id="support_email"
                                    type="email"
                                    value={form.data.support_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'support_email',
                                            event.target.value,
                                        )
                                    }
                                    className="sm:max-w-sm"
                                    aria-invalid={!!form.errors.support_email}
                                />
                            </FormField>
                        </div>

                        <div>
                            <p className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                Live preview
                            </p>
                            <div
                                style={brandStyle({
                                    primaryColor: previewColor,
                                    primaryForegroundColor:
                                        foregroundFor(previewColor),
                                })}
                                className="overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200"
                            >
                                <div className="flex h-12 items-center border-b border-slate-200 bg-white px-4">
                                    <BrandLogo
                                        brand={previewBrand}
                                        nameClassName="text-sm"
                                    />
                                </div>
                                <div className="space-y-1 bg-white p-3">
                                    <span className="flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm font-medium text-brand-800">
                                        <HomeIcon className="size-4 text-brand" />
                                        Dashboard
                                    </span>
                                    <span className="flex items-center gap-2 px-3 py-2 text-sm text-slate-600">
                                        <InboxIcon className="size-4 text-slate-400" />
                                        Tickets
                                    </span>
                                    <span className="flex items-center gap-2 px-3 py-2 text-sm text-slate-600">
                                        <UsersIcon className="size-4 text-slate-400" />
                                        Leads
                                    </span>
                                </div>
                                <div className="flex items-center gap-2 border-t border-slate-200 p-4">
                                    <span className="inline-flex h-8 items-center rounded-lg bg-brand px-3 text-xs font-medium text-brand-foreground">
                                        Accept suggestion
                                    </span>
                                    <span className="inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800 ring-1 ring-brand-200 ring-inset">
                                        AI suggested
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardBody>
                    <CardFooter>
                        {form.isDirty && (
                            <Button
                                variant="ghost"
                                onClick={() => form.reset()}
                            >
                                Discard changes
                            </Button>
                        )}
                        <Button
                            type="submit"
                            loading={form.processing}
                            disabled={!form.isDirty}
                        >
                            Save branding
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </>
    );
}
