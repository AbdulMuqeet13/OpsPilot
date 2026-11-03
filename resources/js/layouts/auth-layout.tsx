import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import { Toaster } from '@/components/toaster';
import { brandStyle } from '@/lib/brand';
import { home } from '@/routes';

export default function AuthLayout({ children }: { children: ReactNode }) {
    const { brand } = usePage().props;

    return (
        <div
            style={brandStyle(brand)}
            className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-brand-50 to-slate-50 px-4 py-12"
        >
            <Link href={home()} className="mb-8">
                <BrandLogo brand={brand} nameClassName="text-lg" />
            </Link>
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                {children}
            </div>
            {brand.supportEmail && (
                <p className="mt-6 text-xs text-slate-500">
                    Need help?{' '}
                    <a
                        href={`mailto:${brand.supportEmail}`}
                        className="font-medium text-slate-700 hover:underline"
                    >
                        {brand.supportEmail}
                    </a>
                </p>
            )}
            <Toaster />
        </div>
    );
}
