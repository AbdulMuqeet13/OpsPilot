<?php

namespace App\Actions\Ai;

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordAiSuggestions
{
    /**
     * Store fresh pending suggestions, retiring any still-pending ones for the same fields.
     *
     * @param  array<string, array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}>  $valuesByField  Keyed by SuggestionField value.
     * @return Collection<int, AiSuggestion>
     */
    public function handle(Model $subject, int $workspaceId, AiFeature $feature, array $valuesByField, ?int $usageLogId): Collection
    {
        return DB::transaction(function () use ($subject, $workspaceId, $feature, $valuesByField, $usageLogId): Collection {
            AiSuggestion::query()
                ->whereMorphedTo('subject', $subject)
                ->pending()
                ->whereIn('field', array_keys($valuesByField))
                ->update(['decision' => SuggestionDecision::Superseded, 'decided_at' => now()]);

            return collect($valuesByField)->map(function (array $value, string $field) use ($subject, $workspaceId, $feature, $usageLogId): AiSuggestion {
                $suggestion = new AiSuggestion;
                $suggestion->workspace_id = $workspaceId;
                $suggestion->ai_usage_log_id = $usageLogId;
                $suggestion->subject()->associate($subject);
                $suggestion->feature = $feature;
                $suggestion->field = SuggestionField::from($field);
                $suggestion->suggested_value = $value;
                $suggestion->decision = SuggestionDecision::Pending;
                $suggestion->save();

                return $suggestion;
            })->values();
        });
    }
}
