<?php

namespace App\Jobs;

use App\Enums\AiFeature;
use App\Models\KnowledgeSnippet;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedKnowledgeSnippet implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Demo resets delete whole workspaces; jobs for records that no longer exist are dropped quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public KnowledgeSnippet $snippet)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->snippet->id;
    }

    /**
     * Without an embedding the snippet is still used; replies just aren't ranked by relevance.
     */
    public function handle(AiGateway $gateway): void
    {
        if (! $gateway->embeddingsEnabled()) {
            return;
        }

        try {
            $result = $gateway->embed(
                new AiCallContext(AiFeature::KnowledgeSearch, $this->snippet->workspace, subject: $this->snippet),
                [$this->snippet->textForEmbedding()],
            );
        } catch (AiException $exception) {
            if ($exception->isRetryable() && $this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 120);
            }

            return;
        }

        $this->snippet->forceFill([
            'embedding' => $result->vectors[0],
            'embedding_model' => config('ai.embedding_model'),
        ])->saveQuietly();
    }
}
