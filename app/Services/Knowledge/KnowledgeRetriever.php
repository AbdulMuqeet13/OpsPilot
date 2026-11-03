<?php

namespace App\Services\Knowledge;

use App\Enums\AiFeature;
use App\Models\KnowledgeSnippet;
use App\Models\Ticket;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use Illuminate\Support\Collection;

/**
 * Picks the knowledge snippets to ground a reply in. Uses embeddings when
 * they are switched on and the snippets have been embedded; otherwise (or
 * if the embedding call fails) it sends every snippet up to a safe limit.
 */
class KnowledgeRetriever
{
    public const int MAX_SNIPPETS_WITHOUT_RANKING = 25;

    public function __construct(private AiGateway $gateway) {}

    /**
     * @return Collection<int, KnowledgeSnippet>
     */
    public function relevantTo(Ticket $ticket, int $limit = 5): Collection
    {
        $snippets = $ticket->workspace->knowledgeSnippets()->orderByDesc('updated_at')->orderByDesc('id')->get();

        if ($snippets->count() <= $limit || ! $this->gateway->embeddingsEnabled()) {
            return $snippets->take(self::MAX_SNIPPETS_WITHOUT_RANKING)->values();
        }

        $embedded = $snippets->filter(fn (KnowledgeSnippet $snippet): bool => $snippet->embedding !== null && $snippet->embedding_model === config('ai.embedding_model'));

        if ($embedded->isEmpty()) {
            return $snippets->take(self::MAX_SNIPPETS_WITHOUT_RANKING)->values();
        }

        try {
            $query = $this->gateway->embed(
                new AiCallContext(AiFeature::KnowledgeSearch, $ticket->workspace, subject: $ticket),
                [$ticket->subject."\n\n".$ticket->body],
            )->vectors[0];
        } catch (AiException) {
            return $snippets->take(self::MAX_SNIPPETS_WITHOUT_RANKING)->values();
        }

        return $embedded
            ->sortByDesc(fn (KnowledgeSnippet $snippet): float => self::cosineSimilarity($query, (array) $snippet->embedding))
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $index => $value) {
            $other = $b[$index] ?? 0.0;
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        return $normA > 0 && $normB > 0 ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
    }
}
