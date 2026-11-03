<?php

namespace App\Models;

use App\Enums\AiTaskStatus;
use App\Enums\LeadScore;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Created through CreateLead (form or CSV import), so attributes are set explicitly.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $lead_import_id
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property string $name
 * @property string $email
 * @property string|null $company
 * @property string|null $job_title
 * @property string|null $company_size
 * @property string|null $budget
 * @property string|null $website
 * @property string|null $message
 * @property LeadScore|null $score
 * @property string|null $score_reason
 * @property AiTaskStatus $scoring_status
 * @property string|null $scoring_error
 * @property AiTaskStatus $follow_up_status
 * @property string|null $follow_up_error
 * @property string|null $follow_up_subject
 * @property string|null $follow_up_body
 * @property CarbonImmutable|null $follow_up_sent_at
 * @property int|null $follow_up_sent_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read User|null $followUpSender
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'score' => LeadScore::class,
            'scoring_status' => AiTaskStatus::class,
            'follow_up_status' => AiTaskStatus::class,
            'follow_up_sent_at' => 'datetime',
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
     * @return BelongsTo<LeadImport, $this>
     */
    public function leadImport(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followUpSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_sent_by');
    }

    /**
     * @return MorphMany<AiSuggestion, $this>
     */
    public function aiSuggestions(): MorphMany
    {
        return $this->morphMany(AiSuggestion::class, 'subject');
    }

    public function isAiWorking(): bool
    {
        return $this->scoring_status->isInProgress() || $this->follow_up_status->isInProgress();
    }

    /**
     * The lead as the AI should see it: clearly delimited untrusted data.
     */
    public function promptBlock(): string
    {
        $lines = array_filter([
            "Name: {$this->name}",
            'Email domain: '.str($this->email)->after('@'),
            $this->company ? "Company: {$this->company}" : null,
            $this->job_title ? "Job title: {$this->job_title}" : null,
            $this->company_size ? "Company size: {$this->company_size}" : null,
            $this->budget ? "Budget: {$this->budget}" : null,
            $this->website ? "Website: {$this->website}" : null,
            $this->message ? "Message:\n{$this->message}" : null,
        ]);

        return "<lead>\n".implode("\n", $lines)."\n</lead>";
    }
}
