<?php

namespace Database\Factories;

use App\Enums\AiCallStatus;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inputTokens = fake()->numberBetween(300, 2500);
        $outputTokens = fake()->numberBetween(80, 600);

        return [
            'workspace_id' => Workspace::factory(),
            'feature' => AiFeature::TicketTriage,
            'provider' => 'anthropic',
            'model' => 'claude-opus-5-5',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => round(($inputTokens * 4 + $outputTokens * 20) / 1_000_000, 6),
            'duration_ms' => fake()->numberBetween(700, 6000),
            'status' => AiCallStatus::Succeeded,
            'input' => ['system' => 'You triage support tickets.', 'messages' => [['role' => 'user', 'content' => 'My sofa arrived damaged.']]],
            'output' => ['category' => 'Damaged items'],
        ];
    }

    /**
     * A call that cost a known amount, for spend-cap scenarios.
     */
    public function costing(float $usd): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_cost_usd' => $usd,
        ]);
    }

    public function failed(AiFailureReason $reason = AiFailureReason::Unavailable): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiCallStatus::Failed,
            'failure_reason' => $reason,
            'error' => 'Provider returned 529 overloaded_error.',
            'output_tokens' => 0,
            'estimated_cost_usd' => 0,
            'output' => null,
        ]);
    }
}
