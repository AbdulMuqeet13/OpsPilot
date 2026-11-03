<?php

namespace App\Http\Resources;

use App\Enums\SuggestionDecision;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Latest suggestion per field, ignoring ones replaced by a newer run.
        $suggestions = $this->aiSuggestions
            ->reject(fn (AiSuggestion $suggestion): bool => $suggestion->decision === SuggestionDecision::Superseded)
            ->sortByDesc('id')
            ->unique(fn (AiSuggestion $suggestion): string => $suggestion->field->value)
            ->mapWithKeys(fn (AiSuggestion $suggestion): array => [$suggestion->field->value => (new AiSuggestionResource($suggestion))->resolve($request)]);

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'subject' => $this->subject,
            'requesterName' => $this->requester_name,
            'requesterEmail' => $this->requester_email,
            'source' => $this->source->label(),
            'status' => $this->status->value,
            'priority' => $this->priority?->value,
            'categoryId' => $this->category_id,
            'category' => $this->category?->toBadgeArray(),
            'sentiment' => $this->sentiment ? ['value' => $this->sentiment->value, 'label' => $this->sentiment->label(), 'score' => (float) $this->sentiment_score] : null,
            'language' => $this->language ? ['value' => $this->language, 'label' => $this->language_name ?? $this->language] : null,
            'summary' => $this->summary,
            'assignedTo' => $this->assigned_to,
            'triage' => ['status' => $this->triage_status->value, 'error' => $this->triage_error],
            'draft' => ['status' => $this->draft_status->value, 'error' => $this->draft_error],
            'aiWorking' => $this->isAiWorking(),
            'suggestions' => $suggestions,
            'messages' => $this->messages->map(fn (TicketMessage $message): array => [
                'id' => $message->id,
                'authorType' => $message->author_type->value,
                'authorName' => $message->user->name ?? $this->requester_name,
                'body' => $message->body,
                'wasEmailed' => $message->was_emailed,
                'sentAt' => $message->sent_at->toIso8601String(),
            ])->values(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
