<?php

namespace App\Models;

use App\Enums\AiCallStatus;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use Carbon\CarbonImmutable;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Written only by the AI gateway, so every attribute is set explicitly rather than mass assigned.
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property int|null $user_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property AiFeature $feature
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $estimated_cost_usd
 * @property int $duration_ms
 * @property AiCallStatus $status
 * @property AiFailureReason|null $failure_reason
 * @property string|null $error
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'status' => AiCallStatus::class,
            'failure_reason' => AiFailureReason::class,
            'estimated_cost_usd' => 'decimal:6',
            'input' => 'array',
            'output' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Calls made since the start of today in the application's timezone.
     *
     * @param  Builder<AiUsageLog>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->where('created_at', '>=', now()->startOfDay());
    }
}
