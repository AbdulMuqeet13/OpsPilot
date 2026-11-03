<?php

namespace Database\Factories;

use App\Enums\MessageAuthorType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_type' => MessageAuthorType::Customer,
            'body' => fake()->paragraph(),
            'was_emailed' => false,
            'sent_at' => now(),
        ];
    }
}
