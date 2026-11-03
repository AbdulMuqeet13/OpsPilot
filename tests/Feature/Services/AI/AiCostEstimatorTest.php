<?php

use App\Services\AI\AiCostEstimator;
use App\Services\AI\Data\TokenUsage;

it('prices known models per million tokens', function () {
    config(['ai.pricing' => ['claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00]]]);

    expect(app(AiCostEstimator::class)->estimate('claude-opus-5-5', new TokenUsage(2_000, 500)))->toBe(0.018);
});

it('matches dated model snapshots by their longest known prefix', function () {
    config(['ai.pricing' => [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ]]);

    expect(app(AiCostEstimator::class)->ratesFor('gpt-4o-mini-2024-07-18'))->toBe(['input' => 0.15, 'output' => 0.60]);
});

it('uses the env override for the configured model', function () {
    config([
        'ai.model' => 'claude-opus-5-5',
        'ai.pricing' => ['claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00]],
        'ai.price_override' => ['input' => '1.00', 'output' => '2.00'],
    ]);

    expect(app(AiCostEstimator::class)->ratesFor('claude-opus-5-5'))->toBe(['input' => 1.0, 'output' => 2.0]);
});

it('falls back to the conservative rate for unknown models', function () {
    config(['ai.pricing' => [], 'ai.fallback_pricing' => ['input' => 10.00, 'output' => 50.00]]);

    expect(app(AiCostEstimator::class)->estimate('some-new-model', new TokenUsage(1_000_000, 0)))->toBe(10.0);
});
