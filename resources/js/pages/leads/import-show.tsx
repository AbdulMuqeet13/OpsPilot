import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppHead } from '@/components/app-head';
import { CheckCircleIcon } from '@/components/icons';
import { PageHeader } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Button, buttonClasses } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError } from '@/components/ui/field-error';
import { Select } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { create as newImport, run } from '@/routes/lead-imports';
import { index as leadsIndex } from '@/routes/leads';

type ImportData = {
    id: number;
    filename: string;
    status: { value: string; label: string };
    headers: string[];
    previewRows: string[][];
    columnMap: Record<string, number | null>;
    totalRows: number;
    importedRows: number;
    skippedRows: number;
    rowErrors: { row: number; message: string }[];
    failureMessage: string | null;
};

const fieldLabels: Record<string, string> = {
    name: 'Full name',
    first_name: 'First name',
    last_name: 'Last name',
    email: 'Email (required)',
    company: 'Company',
    job_title: 'Job title',
    company_size: 'Company size',
    budget: 'Budget',
    website: 'Website',
    message: 'Message / notes',
};

export default function ImportShow({
    import: leadImport,
    fields,
    maxRows,
}: {
    import: ImportData;
    fields: string[];
    maxRows: number;
}) {
    const [columnMap, setColumnMap] = useState<Record<string, number | null>>(
        leadImport.columnMap,
    );
    const [scoreLeads, setScoreLeads] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const inProgress =
        leadImport.status.value === 'queued' ||
        leadImport.status.value === 'processing';
    const { start, stop } = usePoll(
        2000,
        { only: ['import'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (inProgress) {
            start();
        } else {
            stop();
        }
    }, [inProgress, start, stop]);

    function startImport() {
        router.post(
            run.url(leadImport.id),
            { column_map: columnMap, score_leads: scoreLeads },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (validationErrors) => setErrors(validationErrors),
            },
        );
    }

    return (
        <>
            <AppHead title={`Import ${leadImport.filename}`} />
            <PageHeader
                title={leadImport.filename}
                description={`${leadImport.totalRows} rows found.`}
                actions={
                    <Link
                        href={leadsIndex()}
                        className="text-sm text-slate-500 hover:text-slate-800"
                    >
                        Back to leads
                    </Link>
                }
            />

            {leadImport.status.value === 'uploaded' && (
                <Card>
                    <CardHeader
                        title="Match your columns"
                        description="We've guessed the mapping from your column names. Check it, then start the import."
                    />
                    <CardBody className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {fields.map((field) => (
                            <div key={field}>
                                <label
                                    htmlFor={`map-${field}`}
                                    className="mb-1.5 block text-xs font-medium text-slate-600"
                                >
                                    {fieldLabels[field] ?? field}
                                </label>
                                <Select
                                    id={`map-${field}`}
                                    value={columnMap[field] ?? ''}
                                    onChange={(event) =>
                                        setColumnMap({
                                            ...columnMap,
                                            [field]:
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                        })
                                    }
                                    aria-invalid={
                                        !!errors[`column_map.${field}`]
                                    }
                                >
                                    <option value="">Don't import</option>
                                    {leadImport.headers.map((header, index) => (
                                        <option
                                            key={`${header}-${index}`}
                                            value={index}
                                        >
                                            {header || `Column ${index + 1}`}
                                        </option>
                                    ))}
                                </Select>
                                <FieldError
                                    message={errors[`column_map.${field}`]}
                                />
                            </div>
                        ))}
                    </CardBody>
                    <div className="overflow-x-auto border-t border-slate-100">
                        <table className="w-full text-xs">
                            <caption className="px-5 pt-3 text-left text-xs font-medium text-slate-500 sm:px-6">
                                Preview of the first rows
                            </caption>
                            <thead>
                                <tr className="text-left text-slate-500">
                                    {leadImport.headers.map((header, index) => (
                                        <th
                                            key={`${header}-${index}`}
                                            scope="col"
                                            className="px-3 py-2 font-medium whitespace-nowrap first:pl-5 sm:first:pl-6"
                                        >
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {leadImport.previewRows.map((row, rowIndex) => (
                                    <tr key={rowIndex}>
                                        {leadImport.headers.map(
                                            (_, cellIndex) => (
                                                <td
                                                    key={cellIndex}
                                                    className="max-w-56 truncate px-3 py-2 first:pl-5 sm:first:pl-6"
                                                >
                                                    {row[cellIndex] ?? ''}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <CardFooter className="flex-wrap justify-between">
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <Checkbox
                                checked={scoreLeads}
                                onChange={(event) =>
                                    setScoreLeads(event.target.checked)
                                }
                            />
                            Score each lead and draft a follow-up with AI
                        </label>
                        <div className="flex flex-col items-end gap-1">
                            <Button loading={processing} onClick={startImport}>
                                Import {Math.min(leadImport.totalRows, maxRows)}{' '}
                                leads
                            </Button>
                            <FieldError message={errors.column_map} />
                        </div>
                    </CardFooter>
                    {leadImport.totalRows > maxRows && (
                        <CardBody className="pt-0">
                            <Alert tone="warning">
                                Only the first {maxRows} rows will be imported.
                            </Alert>
                        </CardBody>
                    )}
                </Card>
            )}

            {inProgress && (
                <Card>
                    <CardBody
                        className="flex items-center gap-3 py-10"
                        aria-live="polite"
                    >
                        <Spinner className="size-5 text-brand" />
                        <p className="text-sm text-slate-600">
                            Importing leads… this page updates by itself.
                        </p>
                    </CardBody>
                </Card>
            )}

            {leadImport.status.value === 'completed' && (
                <Card>
                    <CardBody className="space-y-4">
                        <div className="flex items-start gap-3">
                            <CheckCircleIcon className="mt-0.5 size-6 text-emerald-600" />
                            <div>
                                <p className="text-base font-semibold text-slate-900">
                                    {leadImport.importedRows} leads imported
                                </p>
                                <p className="text-sm text-slate-500">
                                    {leadImport.skippedRows > 0
                                        ? `${leadImport.skippedRows} rows were skipped.`
                                        : 'No rows were skipped.'}{' '}
                                    AI scoring and follow-up drafts continue in
                                    the background.
                                </p>
                            </div>
                        </div>
                        {leadImport.rowErrors.length > 0 && (
                            <div className="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                                <p className="mb-2 text-xs font-medium text-slate-500">
                                    Skipped rows
                                </p>
                                <ul className="space-y-1 text-sm text-slate-700">
                                    {leadImport.rowErrors.map((error) => (
                                        <li
                                            key={`${error.row}-${error.message}`}
                                        >
                                            <span className="text-slate-400 tabular-nums">
                                                Row {error.row + 1}:
                                            </span>{' '}
                                            {error.message}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="flex gap-2">
                            <Link
                                href={leadsIndex()}
                                className={buttonClasses('primary')}
                            >
                                View leads
                            </Link>
                            <Link
                                href={newImport()}
                                className={buttonClasses('secondary')}
                            >
                                Import another file
                            </Link>
                        </div>
                    </CardBody>
                </Card>
            )}

            {leadImport.status.value === 'failed' && (
                <Alert tone="error" title="The import failed">
                    {leadImport.failureMessage}
                </Alert>
            )}
        </>
    );
}
