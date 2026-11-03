<?php

namespace App\Services\AI\Data;

final readonly class StructuredResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public TokenUsage $usage,
        public string $model,
        public ?int $usageLogId = null,
    ) {}

    public function withUsageLogId(int $usageLogId): self
    {
        return new self($this->data, $this->usage, $this->model, $usageLogId);
    }
}
