<?php

use App\Enums\Sentiment;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Workspace;

use function Pest\Laravel\actingAs;

function agentWithTicket(): array
{
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($agent->workspace)->create();

    return [$agent, $ticket];
}

it('applies an accepted suggestion and records who accepted it', function () {
    [$agent, $ticket] = agentWithTicket();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Priority, ['value' => 'urgent', 'label' => 'Urgent'])->create();

    actingAs($agent)
        ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'accept'])
        ->assertRedirect();

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgent)
        ->and($suggestion->fresh())
        ->decision->toBe(SuggestionDecision::Accepted)
        ->decided_by->toBe($agent->id)
        ->final_value->toBe(['value' => 'urgent', 'label' => 'Urgent']);
});

it('applies the agent\'s edit instead of the suggestion', function () {
    [$agent, $ticket] = agentWithTicket();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Sentiment, ['value' => 'neutral', 'label' => 'Neutral', 'score' => 0.0])->create();

    actingAs($agent)->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'edit', 'value' => 'negative']);

    expect($ticket->fresh())
        ->sentiment->toBe(Sentiment::Negative)
        ->sentiment_score->toBe('-0.60')
        ->and($suggestion->fresh())
        ->decision->toBe(SuggestionDecision::Edited)
        ->final_value->toBe(['value' => 'negative', 'label' => 'Negative', 'score' => -0.6]);
});

it('rejects edits to a category from another workspace', function () {
    [$agent, $ticket] = agentWithTicket();
    $foreignCategory = TicketCategory::factory()->for(Workspace::factory())->create();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Category, ['value' => 1, 'label' => 'Refunds'])->create();

    actingAs($agent)
        ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'edit', 'value' => $foreignCategory->id])
        ->assertSessionHasErrors(['value' => 'Choose one of your ticket categories.']);

    expect($ticket->fresh()->category_id)->toBeNull()
        ->and($suggestion->fresh()->decision)->toBe(SuggestionDecision::Pending);
});

it('leaves the ticket untouched when a suggestion is rejected', function () {
    [$agent, $ticket] = agentWithTicket();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Summary, ['value' => 'Wrong summary', 'label' => 'Wrong summary'])->create();

    actingAs($agent)->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'reject']);

    expect($ticket->fresh()->summary)->toBeNull()
        ->and($suggestion->fresh()->decision)->toBe(SuggestionDecision::Rejected)
        ->and($suggestion->fresh()->final_value)->toBeNull();
});

it('refuses to decide a suggestion twice', function () {
    [$agent, $ticket] = agentWithTicket();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->decided(SuggestionDecision::Rejected)->create();

    actingAs($agent)
        ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'accept'])
        ->assertSessionHasErrors(['decision' => 'Someone already reviewed this suggestion.']);
});

it('only lets reply drafts be discarded here, not sent', function () {
    [$agent, $ticket] = agentWithTicket();
    $draft = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => 'Hi', 'label' => 'Hi'])->create();

    actingAs($agent)
        ->post(route('ai-suggestions.decision.store', $draft), ['decision' => 'accept'])
        ->assertSessionHasErrors('decision');
});

it('requires a value when editing', function () {
    [$agent, $ticket] = agentWithTicket();
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->create();

    actingAs($agent)
        ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'edit'])
        ->assertSessionHasErrors('value');
});

it('returns 404 for a suggestion in another workspace', function () {
    $suggestion = AiSuggestion::factory()->forTicket(Ticket::factory()->create())->create();

    actingAs(User::factory()->create())
        ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'accept'])
        ->assertNotFound();

    expect($suggestion->fresh()->decision)->toBe(SuggestionDecision::Pending);
});

it('accepts every pending triage suggestion at once', function () {
    [$agent, $ticket] = agentWithTicket();
    AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Priority, ['value' => 'low', 'label' => 'Low'])->create();
    AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Summary, ['value' => 'Asks about assembly', 'label' => 'Asks about assembly'])->create();
    $draft = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => 'Hi', 'label' => 'Hi'])->create();

    actingAs($agent)->post(route('tickets.triage-acceptance.store', $ticket))->assertRedirect();

    expect($ticket->fresh())
        ->priority->toBe(TicketPriority::Low)
        ->summary->toBe('Asks about assembly')
        ->and($draft->fresh()->decision)->toBe(SuggestionDecision::Pending);
});
