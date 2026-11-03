import { router } from '@inertiajs/react';
import { RelativeTime } from '@/components/relative-time';
import { Card, CardBody, CardHeader } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { update } from '@/routes/tickets';
import type { CategoryBadgeData, Option, TicketDetail } from '@/types';

export function TicketProperties({
    ticket,
    statuses,
    priorities,
    categories,
    agents,
}: {
    ticket: TicketDetail;
    statuses: Option[];
    priorities: Option[];
    categories: CategoryBadgeData[];
    agents: { id: number; name: string }[];
}) {
    function change(field: string, value: string | null) {
        router.patch(
            update.url(ticket.id),
            { [field]: value },
            { preserveScroll: true },
        );
    }

    return (
        <Card>
            <CardHeader title="Details" />
            <CardBody className="space-y-4">
                <div>
                    <Label
                        htmlFor="status"
                        className="mb-1.5 text-xs text-slate-500"
                    >
                        Status
                    </Label>
                    <Select
                        id="status"
                        value={ticket.status}
                        onChange={(event) =>
                            change('status', event.target.value)
                        }
                    >
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label
                        htmlFor="assigned_to"
                        className="mb-1.5 text-xs text-slate-500"
                    >
                        Assignee
                    </Label>
                    <Select
                        id="assigned_to"
                        value={ticket.assignedTo ?? ''}
                        onChange={(event) =>
                            change('assigned_to', event.target.value || null)
                        }
                    >
                        <option value="">Unassigned</option>
                        {agents.map((agent) => (
                            <option key={agent.id} value={agent.id}>
                                {agent.name}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label
                        htmlFor="priority"
                        className="mb-1.5 text-xs text-slate-500"
                    >
                        Priority
                    </Label>
                    <Select
                        id="priority"
                        value={ticket.priority ?? ''}
                        onChange={(event) =>
                            change('priority', event.target.value || null)
                        }
                    >
                        <option value="">Not set</option>
                        {priorities.map((priority) => (
                            <option key={priority.value} value={priority.value}>
                                {priority.label}
                            </option>
                        ))}
                    </Select>
                </div>
                {categories.length > 0 && (
                    <div>
                        <Label
                            htmlFor="category_id"
                            className="mb-1.5 text-xs text-slate-500"
                        >
                            Category
                        </Label>
                        <Select
                            id="category_id"
                            value={ticket.categoryId ?? ''}
                            onChange={(event) =>
                                change(
                                    'category_id',
                                    event.target.value || null,
                                )
                            }
                        >
                            <option value="">Uncategorised</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                )}
                <dl className="space-y-2 border-t border-slate-100 pt-4 text-sm">
                    <div className="flex justify-between gap-3">
                        <dt className="text-slate-500">Customer</dt>
                        <dd className="truncate text-right text-slate-900">
                            {ticket.requesterName}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt className="text-slate-500">Email</dt>
                        <dd className="truncate text-right text-slate-900">
                            {ticket.requesterEmail}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt className="text-slate-500">Channel</dt>
                        <dd className="text-right text-slate-900">
                            {ticket.source}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt className="text-slate-500">Opened</dt>
                        <dd className="text-right text-slate-900">
                            <RelativeTime iso={ticket.createdAt} />
                        </dd>
                    </div>
                </dl>
            </CardBody>
        </Card>
    );
}
