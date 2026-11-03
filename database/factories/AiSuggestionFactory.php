<?php

namespace Database\Factories;

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSuggestion>
 */
class AiSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Ticket)->getMorphClass(),
            'subject_id' => Ticket::factory(),
            'workspace_id' => fn (array $attributes) => Ticket::query()->whereKey($attributes['subject_id'])->value('workspace_id'),
            'feature' => AiFeature::TicketTriage,
            'field' => SuggestionField::Priority,
            'suggested_value' => ['value' => 'high', 'label' => 'High'],
            'final_value' => null,
            'decision' => SuggestionDecision::Pending,
        ];
    }

    /**
     * A suggestion about the given ticket.
     */
    public function forTicket(Ticket $ticket): static
    {
        return $this->forSubject($ticket);
    }

    /**
     * A suggestion about any workspace-owned record (ticket or lead).
     */
    public function forSubject(Ticket|Lead $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $subject->workspace_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
        ]);
    }

    /**
     * @param  array{value: mixed, label: string, score?: float}  $value
     */
    public function field(SuggestionField $field, array $value): static
    {
        return $this->state(fn (array $attributes) => [
            'field' => $field,
            'suggested_value' => $value,
            'feature' => match ($field) {
                SuggestionField::Reply => AiFeature::ReplyDraft,
                SuggestionField::LeadScore => AiFeature::LeadScoring,
                SuggestionField::FollowUp => AiFeature::LeadFollowUp,
                default => AiFeature::TicketTriage,
            },
        ]);
    }

    public function decided(SuggestionDecision $decision): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => $decision,
            'decided_at' => now(),
        ]);
    }
}
