import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { AppHead } from '@/components/app-head';
import { FollowUpComposer } from '@/components/leads/follow-up-composer';
import { LeadScorePanel } from '@/components/leads/lead-score-panel';
import { RelativeTime } from '@/components/relative-time';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { index as leadsIndex, update } from '@/routes/leads';
import type { LeadDetail, Option } from '@/types';

function Detail({ label, value }: { label: string; value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex justify-between gap-4 py-2">
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="truncate text-right text-sm text-slate-900">
                {value}
            </dd>
        </div>
    );
}

export default function LeadShow({
    lead,
    statuses,
}: {
    lead: LeadDetail;
    statuses: Option[];
}) {
    const { start, stop } = usePoll(
        3000,
        { only: ['lead'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (lead.aiWorking) {
            start();
        } else {
            stop();
        }
    }, [lead.aiWorking, start, stop]);

    return (
        <>
            <AppHead title={lead.name} />
            <div className="mb-6">
                <Link
                    href={leadsIndex()}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← All leads
                </Link>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                    {lead.name}
                </h1>
                <p className="mt-1 text-sm text-slate-500">
                    {[lead.jobTitle, lead.company].filter(Boolean).join(' at ')}
                    {(lead.jobTitle || lead.company) && ' · '}via {lead.source}{' '}
                    · <RelativeTime iso={lead.createdAt} />
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-6">
                    {lead.message && (
                        <Card>
                            <CardHeader title="Their message" />
                            <CardBody>
                                <p className="text-sm leading-6 whitespace-pre-wrap text-slate-700">
                                    {lead.message}
                                </p>
                            </CardBody>
                        </Card>
                    )}
                    <FollowUpComposer key={lead.id} lead={lead} />
                </div>
                <div className="space-y-6">
                    <LeadScorePanel lead={lead} />
                    <Card>
                        <CardHeader title="Details" />
                        <CardBody>
                            <Label
                                htmlFor="lead-status"
                                className="mb-1.5 text-xs text-slate-500"
                            >
                                Status
                            </Label>
                            <Select
                                id="lead-status"
                                value={lead.status}
                                onChange={(event) =>
                                    router.patch(
                                        update.url(lead.id),
                                        { status: event.target.value },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </Select>
                            <dl className="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                                <Detail label="Email" value={lead.email} />
                                <Detail label="Company" value={lead.company} />
                                <Detail
                                    label="Job title"
                                    value={lead.jobTitle}
                                />
                                <Detail
                                    label="Company size"
                                    value={lead.companySize}
                                />
                                <Detail label="Budget" value={lead.budget} />
                                <Detail label="Website" value={lead.website} />
                            </dl>
                        </CardBody>
                    </Card>
                </div>
            </div>
        </>
    );
}
