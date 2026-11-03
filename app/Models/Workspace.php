<?php

namespace App\Models;

use App\Enums\AiFeature;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $brand_name
 * @property string|null $logo_path
 * @property string $primary_color
 * @property string|null $support_email
 * @property string|null $ideal_customer_profile
 * @property string|null $offer_summary
 * @property string|null $api_key_hash
 * @property string|null $api_key_last_four
 * @property CarbonImmutable|null $api_key_created_at
 * @property bool $is_sandbox
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'brand_name', 'logo_path', 'primary_color', 'support_email', 'ideal_customer_profile', 'offer_summary', 'is_sandbox', 'expires_at'])]
#[Hidden(['api_key_hash'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'expires_at' => 'datetime',
            'api_key_created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TicketCategory, $this>
     */
    public function ticketCategories(): HasMany
    {
        return $this->hasMany(TicketCategory::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<KnowledgeSnippet, $this>
     */
    public function knowledgeSnippets(): HasMany
    {
        return $this->hasMany(KnowledgeSnippet::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<LeadImport, $this>
     */
    public function leadImports(): HasMany
    {
        return $this->hasMany(LeadImport::class);
    }

    /**
     * @return HasMany<DailyBrief, $this>
     */
    public function dailyBriefs(): HasMany
    {
        return $this->hasMany(DailyBrief::class);
    }

    /**
     * @return HasMany<AiSuggestion, $this>
     */
    public function aiSuggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class);
    }

    /**
     * @return HasMany<AiFeatureSetting, $this>
     */
    public function aiFeatureSettings(): HasMany
    {
        return $this->hasMany(AiFeatureSetting::class);
    }

    /**
     * The saved settings for a feature, or the defaults (enabled, no custom instructions) if none are saved.
     */
    public function aiSettingFor(AiFeature $feature): AiFeatureSetting
    {
        $setting = $this->aiFeatureSettings->first(fn (AiFeatureSetting $setting): bool => $setting->feature === $feature);

        if ($setting !== null) {
            return $setting;
        }

        $default = new AiFeatureSetting;
        $default->workspace_id = $this->id;
        $default->feature = $feature;

        return $default;
    }

    /**
     * @return HasMany<AiUsageLog, $this>
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * Sandbox workspaces whose demo lifetime has passed.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopeExpiredSandboxes(Builder $query): void
    {
        $query->where('is_sandbox', true)->where('expires_at', '<=', now());
    }
}
