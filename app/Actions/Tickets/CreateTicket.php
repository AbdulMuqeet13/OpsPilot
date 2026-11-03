<?php

namespace App\Actions\Tickets;

use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\MessageAuthorType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateTicket
{
    /**
     * Store a new ticket from any channel and queue the AI work for it.
     *
     * @param  array{name: string, email: string, subject: string, message: string}  $input
     */
    public function handle(Workspace $workspace, TicketSource $source, array $input): Ticket
    {
        $ticket = DB::transaction(function () use ($workspace, $source, $input): Ticket {
            // Lock the workspace row so concurrent tickets get consecutive, unique numbers.
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->first();

            $ticket = new Ticket;
            $ticket->workspace_id = $workspace->id;
            $ticket->number = ((int) Ticket::query()->where('workspace_id', $workspace->id)->max('number') ?: 1000) + 1;
            $ticket->source = $source;
            $ticket->status = TicketStatus::Open;
            $ticket->requester_name = $input['name'];
            $ticket->requester_email = $input['email'];
            $ticket->subject = $input['subject'];
            $ticket->body = $input['message'];
            $ticket->triage_status = $workspace->aiSettingFor(AiFeature::TicketTriage)->is_enabled ? AiTaskStatus::Pending : AiTaskStatus::Skipped;
            $ticket->draft_status = $workspace->aiSettingFor(AiFeature::ReplyDraft)->is_enabled ? AiTaskStatus::Pending : AiTaskStatus::Skipped;
            $ticket->last_activity_at = now();
            $ticket->save();

            $message = new TicketMessage;
            $message->ticket_id = $ticket->id;
            $message->author_type = MessageAuthorType::Customer;
            $message->body = $input['message'];
            $message->sent_at = now();
            $message->save();

            return $ticket;
        });

        if ($ticket->triage_status === AiTaskStatus::Pending) {
            TriageTicket::dispatch($ticket);
        }

        if ($ticket->draft_status === AiTaskStatus::Pending) {
            DraftTicketReply::dispatch($ticket);
        }

        return $ticket;
    }
}
