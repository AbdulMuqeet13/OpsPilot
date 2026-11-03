<?php

namespace App\Models;

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use Carbon\CarbonImmutable;
use Database\Factories\AiSuggestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One AI decision a person can accept, edit or reject. Values are stored
 * as {value, label, ...extra} objects so every field renders the same way.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $ai_usage_log_id
 * @property string $subject_type
 * @property int $subject_id
 * @property AiFeature $feature
 * @property SuggestionField $field
 * @property array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string} $suggested_value
 * @property array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}|null $final_value
 * @property SuggestionDecision $decision
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model $subject
 * @property-read User|null $decider
 * @property-read AiUsageLog|null $usageLog
 */
class AiSuggestion extends Model
{
    /** @use HasFactory<AiSuggestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'field' => SuggestionField::class,
            'suggested_value' => 'array',
            'final_value' => 'array',
            'decision' => SuggestionDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<AiUsageLog, $this>
     */
    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(AiUsageLog::class, 'ai_usage_log_id');
    }

    public function isPending(): bool
    {
        return $this->decision === SuggestionDecision::Pending;
    }

    /**
     * @param  Builder<AiSuggestion>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('decision', SuggestionDecision::Pending);
    }
}
