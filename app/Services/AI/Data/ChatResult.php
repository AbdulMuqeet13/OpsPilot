<?php

namespace App\Services\AI\Data;

final readonly class ChatResult
{
    public function __construct(
        public string $text,
        public TokenUsage $usage,
        public string $model,
        public ?int $usageLogId = null,
    ) {}

    public function withUsageLogId(int $usageLogId): self
    {
        return new self($this->text, $this->usage, $this->model, $usageLogId);
    }
}
