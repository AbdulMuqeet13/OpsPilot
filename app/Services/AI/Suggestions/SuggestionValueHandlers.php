<?php

namespace App\Services\AI\Suggestions;

use App\Models\Lead;
use App\Models\Ticket;
use App\Services\Leads\LeadSuggestionValues;
use App\Services\Tickets\TicketSuggestionValues;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SuggestionValueHandlers
{
    public function __construct(
        private TicketSuggestionValues $tickets,
        private LeadSuggestionValues $leads,
    ) {}

    public function for(Model $subject): SuggestionValueHandler
    {
        return match (true) {
            $subject instanceof Ticket => $this->tickets,
            $subject instanceof Lead => $this->leads,
            default => throw new InvalidArgumentException('No suggestion handler for '.$subject::class.'.'),
        };
    }
}
