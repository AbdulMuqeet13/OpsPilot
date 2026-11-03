<?php

namespace Database\Factories;

use App\Enums\AiTaskStatus;
use App\Enums\Sentiment;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    private static int $number = 1000;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'number' => ++self::$number,
            'source' => TicketSource::Form,
            'status' => TicketStatus::Open,
            'requester_name' => fake()->name(),
            'requester_email' => fake()->safeEmail(),
            'subject' => 'My sofa arrived with a torn cushion',
            'body' => "Hi, my new sofa was delivered yesterday and one of the seat cushions has a long tear along the seam. Can you send a replacement?\n\nThanks",
            'triage_status' => AiTaskStatus::Completed,
            'draft_status' => AiTaskStatus::Completed,
            'last_activity_at' => now(),
        ];
    }

    public function awaitingAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'triage_status' => AiTaskStatus::Pending,
            'draft_status' => AiTaskStatus::Pending,
        ]);
    }

    public function triaged(?TicketCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id,
            'priority' => TicketPriority::High,
            'sentiment' => Sentiment::Negative,
            'sentiment_score' => -0.5,
            'language' => 'en',
            'language_name' => 'English',
            'summary' => 'Sofa delivered with a torn seat cushion; wants a replacement.',
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
