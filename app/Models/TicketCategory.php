<?php

namespace App\Models;

use App\Enums\CategoryColor;
use Carbon\CarbonImmutable;
use Database\Factories\TicketCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $description
 * @property CategoryColor $color
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'description', 'color', 'position'])]
class TicketCategory extends Model
{
    /** @use HasFactory<TicketCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CategoryColor::class,
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
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    /**
     * @return array{id: int, name: string, color: string}
     */
    public function toBadgeArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'color' => $this->color->value];
    }
}
