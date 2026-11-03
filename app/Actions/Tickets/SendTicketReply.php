<?php

namespace App\Actions\Tickets;

use App\Enums\MessageAuthorType;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\DraftComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendTicketReply
{
    /**
     * Send an agent-approved reply and record what happened to the AI draft.
     */
    public function handle(Ticket $ticket, User $agent, string $body, bool $resolve): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $agent, $body, $resolve): TicketMessage {
            $message = new TicketMessage;
            $message->ticket_id = $ticket->id;
            $message->author_type = MessageAuthorType::Agent;
            $message->user_id = $agent->id;
            $message->body = $body;
            // Demo visitors can type any address into the public form, so demo mode never emails real people.
            $message->was_emailed = ! config('demo.enabled');
            $message->sent_at = now();
            $message->save();

            $this->recordDraftOutcome($ticket, $agent, $body);

            $ticket->status = $resolve ? TicketStatus::Resolved : TicketStatus::Pending;
            $ticket->resolved_at = $resolve ? now() : null;
            $ticket->last_activity_at = now();
            $ticket->save();

            return $message;
        });

        if ($message->was_emailed) {
            Mail::to($ticket->requester_email, $ticket->requester_name)->send(new TicketReplyMail($ticket, $message));
        }

        return $message;
    }

    private function recordDraftOutcome(Ticket $ticket, User $agent, string $sentBody): void
    {
        $draft = $ticket->aiSuggestions()
            ->pending()
            ->where('field', SuggestionField::Reply)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $draft instanceof AiSuggestion) {
            return;
        }

        $decision = DraftComparison::decisionFor((string) $draft->suggested_value['value'], $sentBody);

        $draft->forceFill([
            'decision' => $decision,
            'final_value' => $decision === SuggestionDecision::Rejected ? null : ['value' => $sentBody, 'label' => $sentBody],
            'decided_by' => $agent->id,
            'decided_at' => now(),
        ])->save();
    }
}
