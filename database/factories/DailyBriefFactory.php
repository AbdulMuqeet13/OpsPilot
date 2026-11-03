<?php

namespace Database\Factories;

use App\Enums\AiTaskStatus;
use App\Enums\BriefTrigger;
use App\Models\DailyBrief;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyBrief>
 */
class DailyBriefFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'brief_date' => now()->subDay()->toDateString(),
            'trigger' => BriefTrigger::Scheduled,
            'generation_status' => AiTaskStatus::Completed,
            'stats' => ['tickets' => 12, 'leads' => 4, 'hot_leads' => 1, 'urgent_open' => 2, 'average_sentiment' => -0.2, 'by_category' => ['Delivery delays' => 5]],
            'content' => [
                'headline' => 'Delivery delays drove most of yesterday\'s tickets',
                'summary' => 'Twelve new tickets, five about late deliveries from the northern warehouse.',
                'themes' => [['title' => 'Late deliveries', 'detail' => 'Five customers waiting past the promised window.', 'tickets' => []]],
                'urgent_items' => [],
            ],
            'generated_at' => now(),
        ];
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'generation_status' => AiTaskStatus::Pending,
            'content' => null,
            'generated_at' => null,
        ]);
    }
}
