<?php

namespace App\Models;

use App\Enums\AiTaskStatus;
use App\Enums\BriefTrigger;
use Carbon\CarbonImmutable;
use Database\Factories\DailyBriefFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property CarbonImmutable $brief_date
 * @property BriefTrigger $trigger
 * @property int|null $requested_by
 * @property AiTaskStatus $generation_status
 * @property string|null $generation_error
 * @property array{tickets: int, leads: int, hot_leads: int, urgent_open: int, average_sentiment: float|null, by_category: array<array-key, int>}|null $stats
 * @property array{headline: string, summary: string, themes: list<array{title: string, detail: string, tickets: list<array{id: int, number: int}>}>, urgent_items: list<array{kind: string, id: int, label: string, reason: string}>}|null $content
 * @property int|null $ai_usage_log_id
 * @property CarbonImmutable|null $generated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read User|null $requester
 */
class DailyBrief extends Model
{
    /** @use HasFactory<DailyBriefFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brief_date' => 'date',
            'trigger' => BriefTrigger::class,
            'generation_status' => AiTaskStatus::class,
            'stats' => 'array',
            'content' => 'array',
            'generated_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
