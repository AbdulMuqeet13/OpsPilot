import { Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { AppHead } from '@/components/app-head';
import { AiTriagePanel } from '@/components/tickets/ai-triage-panel';
import { Conversation } from '@/components/tickets/conversation';
import { ReplyComposer } from '@/components/tickets/reply-composer';
import { TicketProperties } from '@/components/tickets/ticket-properties';
import { StatusBadge } from '@/components/ticket-badges';
import { Card, CardBody } from '@/components/ui/card';
import { index as ticketsIndex } from '@/routes/tickets';
import type { CategoryBadgeData, Option, TicketDetail } from '@/types';

export default function TicketShow({
    ticket,
    categories,
    agents,
    priorities,
    statuses,
    languages,
    demoMode,
}: {
    ticket: TicketDetail;
    categories: CategoryBadgeData[];
    agents: { id: number; name: string }[];
    priorities: Option[];
    statuses: Option[];
    languages: { code: string; name: string }[];
    demoMode: boolean;
}) {
    const { auth } = usePage().props;
    const { start, stop } = usePoll(
        3000,
        { only: ['ticket'] },
        { autoStart: false },
    );
    const statusLabel =
        statuses.find((status) => status.value === ticket.status)?.label ??
        ticket.status;

    useEffect(() => {
        if (ticket.aiWorking) {
            start();
        } else {
            stop();
        }
    }, [ticket.aiWorking, start, stop]);

    return (
        <>
            <AppHead title={`${ticket.reference} ${ticket.subject}`} />

            <div className="mb-6">
                <Link
                    href={ticketsIndex()}
                    className="text-sm text-slate-500 hover:text-slate-800"
                >
                    ← All tickets
                </Link>
                <div className="mt-2 flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                        {ticket.subject}
                    </h1>
                    <StatusBadge value={ticket.status} label={statusLabel} />
                </div>
                <p className="mt-1 text-sm text-slate-500">
                    {ticket.reference} · from {ticket.requesterName} via{' '}
                    {ticket.source}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="min-w-0 space-y-6">
                    <Card>
                        <CardBody>
                            <Conversation
                                messages={ticket.messages}
                                demoMode={demoMode}
                            />
                        </CardBody>
                    </Card>
                    <ReplyComposer
                        key={ticket.id}
                        ticket={ticket}
                        languages={languages}
                        demoMode={demoMode}
                    />
                </div>
                <div className="space-y-6">
                    <AiTriagePanel
                        ticket={ticket}
                        choices={{ categories, priorities, languages }}
                        canManageSettings={auth.can.manageSettings}
                    />
                    <TicketProperties
                        ticket={ticket}
                        statuses={statuses}
                        priorities={priorities}
                        categories={categories}
                        agents={agents}
                    />
                </div>
            </div>
        </>
    );
}
