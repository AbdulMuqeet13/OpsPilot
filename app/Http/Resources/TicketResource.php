<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public API representation of a ticket.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'subject' => $this->subject,
            'requester' => ['name' => $this->requester_name, 'email' => $this->requester_email],
            'ai' => ['triage' => $this->triage_status->value, 'reply_draft' => $this->draft_status->value],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
