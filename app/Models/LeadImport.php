<?php

namespace App\Models;

use App\Enums\LeadImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LeadImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int|null $user_id
 * @property string $original_filename
 * @property string $path
 * @property LeadImportStatus $status
 * @property list<string> $headers
 * @property list<list<string>> $preview_rows
 * @property array<string, int|null>|null $column_map
 * @property bool $score_leads
 * @property int $total_rows
 * @property int $imported_rows
 * @property int $skipped_rows
 * @property list<array{row: int, message: string}>|null $row_errors
 * @property string|null $failure_message
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 */
class LeadImport extends Model
{
    /** @use HasFactory<LeadImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadImportStatus::class,
            'headers' => 'array',
            'preview_rows' => 'array',
            'column_map' => 'array',
            'score_leads' => 'boolean',
            'row_errors' => 'array',
            'completed_at' => 'datetime',
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
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
