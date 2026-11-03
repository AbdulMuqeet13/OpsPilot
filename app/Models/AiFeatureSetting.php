<?php

namespace App\Models;

use App\Enums\AiFeature;
use Carbon\CarbonImmutable;
use Database\Factories\AiFeatureSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property AiFeature $feature
 * @property bool $is_enabled
 * @property bool $auto_apply
 * @property string|null $custom_instructions
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['is_enabled', 'auto_apply', 'custom_instructions'])]
class AiFeatureSetting extends Model
{
    /** @use HasFactory<AiFeatureSettingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'auto_apply' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'is_enabled' => 'boolean',
            'auto_apply' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
