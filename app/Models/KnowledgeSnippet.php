<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\KnowledgeSnippetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $title
 * @property string $content
 * @property list<float>|null $embedding
 * @property string|null $embedding_model
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'content'])]
#[Hidden(['embedding'])]
class KnowledgeSnippet extends Model
{
    /** @use HasFactory<KnowledgeSnippetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function textForEmbedding(): string
    {
        return $this->title."\n\n".$this->content;
    }
}
