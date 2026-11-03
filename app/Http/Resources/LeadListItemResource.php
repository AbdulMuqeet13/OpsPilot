<?php

namespace App\Http\Resources;

use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects aiSuggestions to be eager loaded with only pending suggestions.
 *
 * @mixin Lead
 */
class LeadListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pending = $this->relationLoaded('aiSuggestions') ? $this->aiSuggestions : collect();
        $pendingScore = $pending->first(fn (AiSuggestion $suggestion): bool => $suggestion->field === SuggestionField::LeadScore);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company,
            'jobTitle' => $this->job_title,
            'source' => $this->source->label(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'score' => $this->score ? ['value' => $this->score->value, 'label' => $this->score->label()] : null,
            'suggestedScore' => $pendingScore?->suggested_value,
            'hasFollowUpDraft' => $pending->contains(fn (AiSuggestion $suggestion): bool => $suggestion->field === SuggestionField::FollowUp),
            'followUpSentAt' => $this->follow_up_sent_at?->toIso8601String(),
            'aiWorking' => $this->isAiWorking(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
