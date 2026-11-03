<?php

namespace App\Http\Resources;

use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects aiSuggestions to be eager loaded with only pending triage suggestions.
 *
 * @mixin Ticket
 */
class TicketListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pending = $this->relationLoaded('aiSuggestions')
            ? $this->aiSuggestions->keyBy(fn (AiSuggestion $suggestion): string => $suggestion->field->value)
            : collect();

        $suggested = fn (SuggestionField $field): ?array => $pending->get($field->value)?->suggested_value;

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'subject' => $this->subject,
            'requesterName' => $this->requester_name,
            'source' => $this->source->label(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'priority' => $this->priority ? ['value' => $this->priority->value, 'label' => $this->priority->label()] : null,
            'category' => $this->category?->toBadgeArray(),
            'sentiment' => $this->sentiment ? ['value' => $this->sentiment->value, 'label' => $this->sentiment->label()] : null,
            'summary' => $this->summary,
            'suggested' => [
                'priority' => $suggested(SuggestionField::Priority),
                'category' => $suggested(SuggestionField::Category),
                'sentiment' => $suggested(SuggestionField::Sentiment),
                'summary' => $suggested(SuggestionField::Summary),
            ],
            'pendingSuggestions' => $pending->count(),
            'aiWorking' => $this->isAiWorking(),
            'assignee' => $this->assignee?->name,
            'lastActivityAt' => $this->last_activity_at?->toIso8601String(),
        ];
    }
}
