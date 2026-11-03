import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { UploadIcon } from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { RelativeTime } from '@/components/relative-time';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { show as showImport, store } from '@/routes/lead-imports';
import { index as leadsIndex } from '@/routes/leads';

type RecentImport = {
    id: number;
    filename: string;
    status: { value: string; label: string };
    importedRows: number;
    createdAt: string | null;
};

const sampleCsv = `Full name,Email,Company,Job title,Company size,Budget,Notes
Dana Whitfield,dana@northwind-interiors.example,Northwind Interiors,Principal Designer,11-50,$25k-$50k,Fitting out a 12-room boutique hotel in spring and looking for a trade supplier.
Marcus Bell,marcus.bell@example.com,,Student,,,Doing a university project on furniture retail.
Aiko Tanaka,aiko@brightspace-offices.example,Brightspace Offices,Facilities Lead,201-1000,$50k+,Refurbishing two office floors (180 desks) next quarter.`;

export default function ImportLeads({
    recentImports,
    limits,
}: {
    recentImports: RecentImport[];
    limits: {
        maxKilobytes: number;
        maxRows: number;
        maxFilesPerUser: number | null;
    };
}) {
    const [fileName, setFileName] = useState<string | null>(null);
    const sampleHref = `data:text/csv;charset=utf-8,${encodeURIComponent(sampleCsv)}`;

    return (
        <>
            <AppHead title="Import leads" />
            <PageHeader
                title="Import leads from CSV"
                description="Upload an export from your CRM or a spreadsheet. You'll check the column mapping before anything is imported."
                actions={
                    <Link
                        href={leadsIndex()}
                        className="text-sm text-slate-500 hover:text-slate-800"
                    >
                        Back to leads
                    </Link>
                }
            />

            <Card>
                <CardHeader
                    title="Upload a file"
                    description={`CSV only, up to ${limits.maxKilobytes} KB and ${limits.maxRows} rows${limits.maxFilesPerUser ? `, ${limits.maxFilesPerUser} imports per person in the demo` : ''}.`}
                    action={
                        <a
                            href={sampleHref}
                            download="sample-leads.csv"
                            className="text-sm font-medium text-brand hover:underline"
                        >
                            Download a sample CSV
                        </a>
                    }
                />
                <CardBody>
                    <Form {...store.form()}>
                        {({ errors, processing, progress }) => (
                            <div className="space-y-4">
                                <label
                                    htmlFor="file"
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center focus-within:border-brand hover:border-brand hover:bg-brand-50/40"
                                >
                                    <UploadIcon className="size-8 text-slate-400" />
                                    <span className="mt-3 text-sm font-medium text-slate-900">
                                        {fileName ?? 'Choose a CSV file'}
                                    </span>
                                    <span className="mt-1 text-xs text-slate-500">
                                        The first row must contain column names,
                                        such as Name, Email, Company.
                                    </span>
                                    <input
                                        id="file"
                                        name="file"
                                        type="file"
                                        accept=".csv,text/csv"
                                        className="sr-only"
                                        onChange={(event) =>
                                            setFileName(
                                                event.target.files?.[0]?.name ??
                                                    null,
                                            )
                                        }
                                    />
                                </label>
                                <FieldError message={errors.file} />
                                {progress && (
                                    <div className="h-1.5 overflow-hidden rounded-full bg-brand-100">
                                        <div
                                            className="h-full bg-brand transition-[width]"
                                            style={{
                                                width: `${progress.percentage ?? 0}%`,
                                            }}
                                        />
                                    </div>
                                )}
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        loading={processing}
                                        disabled={!fileName}
                                    >
                                        Upload and review columns
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </CardBody>
            </Card>

            {recentImports.length > 0 && (
                <Card className="mt-6">
                    <CardHeader title="Recent imports" />
                    <ul className="divide-y divide-slate-100">
                        {recentImports.map((recent) => (
                            <li key={recent.id}>
                                <Link
                                    href={showImport(recent.id)}
                                    className="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50 sm:px-6"
                                >
                                    <span className="min-w-0 truncate font-medium text-slate-900">
                                        {recent.filename}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-3 text-xs text-slate-500">
                                        {recent.status.value ===
                                            'completed' && (
                                            <span>
                                                {recent.importedRows} imported
                                            </span>
                                        )}
                                        <Badge
                                            tone={
                                                recent.status.value ===
                                                'completed'
                                                    ? 'success'
                                                    : recent.status.value ===
                                                        'failed'
                                                      ? 'danger'
                                                      : 'neutral'
                                            }
                                        >
                                            {recent.status.label}
                                        </Badge>
                                        <RelativeTime iso={recent.createdAt} />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </>
    );
}
