<?php

namespace App\Services\AI;

use App\Services\AI\Data\TokenUsage;

class AiCostEstimator
{
    /**
     * Estimated cost in USD, rounded to the precision stored in ai_usage_logs.
     */
    public function estimate(string $model, TokenUsage $usage): float
    {
        ['input' => $inputRate, 'output' => $outputRate] = $this->ratesFor($model);

        return round(($usage->inputTokens * $inputRate + $usage->outputTokens * $outputRate) / 1_000_000, 6);
    }

    /**
     * @return array{input: float, output: float}
     */
    public function ratesFor(string $model): array
    {
        $override = config('ai.price_override');

        if ($model === config('ai.model') && is_numeric($override['input'] ?? null) && is_numeric($override['output'] ?? null)) {
            return ['input' => (float) $override['input'], 'output' => (float) $override['output']];
        }

        /** @var array<string, array{input: float, output: float}> $pricing */
        $pricing = config('ai.pricing', []);

        // Longest matching prefix, so dated snapshots like "gpt-4o-mini-2024-07-18" price as "gpt-4o-mini".
        $matches = array_filter(array_keys($pricing), fn (string $known): bool => str_starts_with($model, $known));
        usort($matches, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $rates = $matches === [] ? config('ai.fallback_pricing') : $pricing[$matches[0]];

        return ['input' => (float) $rates['input'], 'output' => (float) $rates['output']];
    }
}
