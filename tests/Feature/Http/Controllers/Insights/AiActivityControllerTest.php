<?php

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function suggestionsWithDecisions(Ticket $ticket, array $decisions): void
{
    foreach ($decisions as $decision) {
        AiSuggestion::factory()->forTicket($ticket)->decided($decision)->create();
    }
}

it('calculates acceptance from human decisions only', function () {
    $user = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($user->workspace)->create();
    suggestionsWithDecisions($ticket, [
        SuggestionDecision::Accepted, SuggestionDecision::Accepted, SuggestionDecision::Accepted,
        SuggestionDecision::Edited,
        SuggestionDecision::Rejected,
        SuggestionDecision::AutoApplied,
        SuggestionDecision::Superseded,
    ]);
    AiSuggestion::factory()->forTicket($ticket)->create();
    AiSuggestion::factory()->forTicket(Ticket::factory()->create())->decided(SuggestionDecision::Rejected)->create();

    actingAs($user)
        ->get(route('ai-activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai-activity/index')
            ->where('metrics.decided', 5)
            ->where('metrics.accepted', 3)
            ->where('metrics.acceptanceRate', 0.6)
            ->where('metrics.approvalRate', 0.8)
            ->where('metrics.pending', 1)
            ->where('metrics.autoApplied', 1)
            ->where('metrics.byFeature.0.label', 'Ticket triage')
            ->where('metrics.byFeature.0.acceptanceRate', 0.6)
            ->has('suggestions.data', 7));
});

it('filters the log by feature and decision', function () {
    $user = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($user->workspace)->create();
    AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => 'Hi', 'label' => 'Hi'])->decided(SuggestionDecision::Edited)->create();
    AiSuggestion::factory()->forTicket($ticket)->decided(SuggestionDecision::Edited)->create();
    AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => 'Hi', 'label' => 'Hi'])->decided(SuggestionDecision::Rejected)->create();

    actingAs($user)
        ->get(route('ai-activity.index', ['feature' => AiFeature::ReplyDraft->value, 'decision' => 'edited']))
        ->assertInertia(fn (Assert $page) => $page->has('suggestions.data', 1)->where('suggestions.data.0.field', 'Reply draft'));
});

it('shows the AI\'s input and output for a selected decision', function () {
    $user = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($user->workspace)->create();
    $log = AiUsageLog::factory()->for($user->workspace)->create([
        'input' => ['system' => 'You triage tickets.', 'messages' => [['role' => 'user', 'content' => '<ticket>Sofa torn</ticket>']]],
        'output' => ['priority' => 'high'],
    ]);
    $suggestion = AiSuggestion::factory()->forTicket($ticket)->create(['ai_usage_log_id' => $log->id]);

    actingAs($user)
        ->get(route('ai-activity.index', ['selected' => $suggestion->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $suggestion->id)
            ->where('selected.subject.type', 'ticket')
            ->where('selected.call.input.messages.0.content', '<ticket>Sofa torn</ticket>')
            ->where('selected.call.output', ['priority' => 'high']));
});

it('never reveals another workspace\'s decision details', function () {
    $foreign = AiSuggestion::factory()->forTicket(Ticket::factory()->create())->create();

    actingAs(User::factory()->create())
        ->get(route('ai-activity.index', ['selected' => $foreign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});
