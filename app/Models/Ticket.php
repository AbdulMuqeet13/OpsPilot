<?php

namespace App\Models;

use App\Enums\AiTaskStatus;
use App\Enums\Sentiment;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Created only through CreateTicket and changed through dedicated actions,
 * so attributes are set explicitly rather than mass assigned.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $number
 * @property TicketSource $source
 * @property TicketStatus $status
 * @property string $requester_name
 * @property string $requester_email
 * @property string $subject
 * @property string $body
 * @property int|null $category_id
 * @property TicketPriority|null $priority
 * @property Sentiment|null $sentiment
 * @property string|null $sentiment_score
 * @property string|null $language
 * @property string|null $language_name
 * @property string|null $summary
 * @property AiTaskStatus $triage_status
 * @property string|null $triage_error
 * @property AiTaskStatus $draft_status
 * @property string|null $draft_error
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read TicketCategory|null $category
 * @property-read User|null $assignee
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TicketSource::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'sentiment' => Sentiment::class,
            'sentiment_score' => 'decimal:2',
            'triage_status' => AiTaskStatus::class,
            'draft_status' => AiTaskStatus::class,
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return MorphMany<AiSuggestion, $this>
     */
    public function aiSuggestions(): MorphMany
    {
        return $this->morphMany(AiSuggestion::class, 'subject');
    }

    public function reference(): string
    {
        return '#'.$this->number;
    }

    public function isAiWorking(): bool
    {
        return $this->triage_status->isInProgress() || $this->draft_status->isInProgress();
    }

    /**
     * The customer's message as the AI should see it: clearly delimited untrusted data.
     */
    public function promptBlock(): string
    {
        return "<ticket>\nFrom: {$this->requester_name}\nSubject: {$this->subject}\n\n{$this->body}\n</ticket>";
    }
}
