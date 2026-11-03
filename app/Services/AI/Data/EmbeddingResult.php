<?php

namespace App\Services\AI\Data;

final readonly class EmbeddingResult
{
    /**
     * @param  list<list<float>>  $vectors  One vector per input text, in the same order.
     */
    public function __construct(
        public array $vectors,
        public TokenUsage $usage,
        public string $model,
        public ?int $usageLogId = null,
    ) {}

    public function withUsageLogId(int $usageLogId): self
    {
        return new self($this->vectors, $this->usage, $this->model, $usageLogId);
    }
}
