<?php

namespace Database\Factories;

use App\Enums\AiTaskStatus;
use App\Enums\LeadScore;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'source' => LeadSource::Form,
            'status' => LeadStatus::New,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->company(),
            'job_title' => 'Operations Manager',
            'company_size' => '51-200',
            'budget' => '$25k-$50k',
            'website' => null,
            'message' => 'We are furnishing two new boutique hotel floors next quarter and need a trade quote for 40 rooms.',
            'scoring_status' => AiTaskStatus::Completed,
            'follow_up_status' => AiTaskStatus::Completed,
        ];
    }

    public function awaitingAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'scoring_status' => AiTaskStatus::Pending,
            'follow_up_status' => AiTaskStatus::Pending,
        ]);
    }

    public function scored(LeadScore $score = LeadScore::Hot): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
            'score_reason' => 'Clear project, timeline and budget that match the trade programme.',
        ]);
    }
}
