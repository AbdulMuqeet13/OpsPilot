<?php

namespace App\Services\AI\Data;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
