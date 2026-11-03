<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;

class UpdateTicketDetails
{
    /**
     * Manual changes an agent makes in the ticket sidebar.
     *
     * @param  array{status?: TicketStatus, priority?: TicketPriority|null, category_id?: int|null, assigned_to?: int|null}  $changes
     */
    public function handle(Ticket $ticket, array $changes): Ticket
    {
        if (array_key_exists('status', $changes)) {
            $ticket->status = $changes['status'];
            $ticket->resolved_at = $changes['status'] === TicketStatus::Resolved ? ($ticket->resolved_at ?? now()) : null;
        }

        if (array_key_exists('priority', $changes)) {
            $ticket->priority = $changes['priority'];
        }

        if (array_key_exists('category_id', $changes)) {
            $ticket->category_id = $changes['category_id'];
        }

        if (array_key_exists('assigned_to', $changes)) {
            $ticket->assigned_to = $changes['assigned_to'];
        }

        $ticket->last_activity_at = now();
        $ticket->save();

        return $ticket;
    }
}
