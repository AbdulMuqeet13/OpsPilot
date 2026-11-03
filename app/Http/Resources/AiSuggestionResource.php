<?php

namespace App\Http\Resources;

use App\Models\AiSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiSuggestion
 */
class AiSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field' => $this->field->value,
            'fieldLabel' => $this->field->label(),
            'decision' => $this->decision->value,
            'decisionLabel' => $this->decision->label(),
            'suggested' => $this->suggested_value,
            'final' => $this->final_value,
            'decidedBy' => $this->whenLoaded('decider', fn () => $this->decider?->name),
            'decidedAt' => $this->decided_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
