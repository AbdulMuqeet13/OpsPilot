<?php

namespace App\Http\Resources;

use App\Enums\SuggestionDecision;
use App\Models\AiSuggestion;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suggestions = $this->aiSuggestions
            ->reject(fn (AiSuggestion $suggestion): bool => $suggestion->decision === SuggestionDecision::Superseded)
            ->sortByDesc('id')
            ->unique(fn (AiSuggestion $suggestion): string => $suggestion->field->value)
            ->mapWithKeys(fn (AiSuggestion $suggestion): array => [$suggestion->field->value => (new AiSuggestionResource($suggestion))->resolve($request)]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company,
            'jobTitle' => $this->job_title,
            'companySize' => $this->company_size,
            'budget' => $this->budget,
            'website' => $this->website,
            'message' => $this->message,
            'source' => $this->source->label(),
            'status' => $this->status->value,
            'score' => $this->score ? ['value' => $this->score->value, 'label' => $this->score->label()] : null,
            'scoreReason' => $this->score_reason,
            'scoring' => ['status' => $this->scoring_status->value, 'error' => $this->scoring_error],
            'followUp' => [
                'status' => $this->follow_up_status->value,
                'error' => $this->follow_up_error,
                'sentSubject' => $this->follow_up_subject,
                'sentBody' => $this->follow_up_body,
                'sentAt' => $this->follow_up_sent_at?->toIso8601String(),
                'sentBy' => $this->followUpSender?->name,
            ],
            'aiWorking' => $this->isAiWorking(),
            'suggestions' => $suggestions,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
