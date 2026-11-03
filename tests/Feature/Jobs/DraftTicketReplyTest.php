<?php

use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Jobs\DraftTicketReply;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\KnowledgeSnippet;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Testing\FakeAiClient;

it('drafts a reply grounded in every snippet when embeddings are off', function () {
    $fake = FakeAiClient::install()->withoutEmbeddings()->queueText("Hi Priya,\n\nSorry about the cushion…\n\nThe Oakline Home team");
    $workspace = Workspace::factory()->create(['brand_name' => 'Oakline Home']);
    KnowledgeSnippet::factory()->for($workspace)->create(['title' => 'Damaged on arrival', 'content' => 'We replace items damaged in transit free of charge.']);
    KnowledgeSnippet::factory()->for($workspace)->create(['title' => 'Delivery times', 'content' => 'Large furniture ships in 7 to 10 business days.']);
    KnowledgeSnippet::factory()->for(Workspace::factory())->create(['title' => 'Someone else\'s policy']);
    $ticket = Ticket::factory()->for($workspace)->awaitingAi()->create();

    (new DraftTicketReply($ticket))->handle();

    $draft = AiSuggestion::sole();

    expect($ticket->fresh()->draft_status)->toBe(AiTaskStatus::Completed)
        ->and($draft->field)->toBe(SuggestionField::Reply)
        ->and($draft->feature)->toBe(AiFeature::ReplyDraft)
        ->and($draft->decision)->toBe(SuggestionDecision::Pending)
        ->and($draft->suggested_value['value'])->toBe("Hi Priya,\n\nSorry about the cushion…\n\nThe Oakline Home team")
        ->and($draft->suggested_value['sources'])->toEqualCanonicalizing(['Damaged on arrival', 'Delivery times']);

    $fake->assertSent(fn (ChatRequest $request) => str_contains($request->system, 'We replace items damaged in transit free of charge.')
        && ! str_contains($request->system, "Someone else's policy")
        && str_contains($request->system, 'sign off as "The Oakline Home team"'));
});

it('tells the AI not to invent policies when there are no snippets', function () {
    $fake = FakeAiClient::install()->withoutEmbeddings()->queueText('Thanks, we will look into it.');
    $ticket = Ticket::factory()->awaitingAi()->create();

    (new DraftTicketReply($ticket))->handle();

    $fake->assertSent(fn (ChatRequest $request) => str_contains($request->system, 'No policy snippets are available'));
});

it('ranks embedded snippets by similarity and sends only the closest ones', function () {
    $fake = FakeAiClient::install();
    $workspace = Workspace::factory()->create();
    $closest = KnowledgeSnippet::factory()->for($workspace)->embedded([1.0, 0.0])->create(['title' => 'Damaged on arrival']);
    foreach (range(1, 6) as $index) {
        KnowledgeSnippet::factory()->for($workspace)->embedded([0.0, 1.0])->create(['title' => "Unrelated {$index}"]);
    }
    $ticket = Ticket::factory()->for($workspace)->awaitingAi()->create();
    $fake->queueEmbeddings([[0.9, 0.1]])->queueText('Draft');

    (new DraftTicketReply($ticket))->handle();

    $sources = AiSuggestion::sole()->suggested_value['sources'];

    expect($sources)->toHaveCount(5)
        ->and($sources[0])->toBe($closest->title)
        ->and(AiUsageLog::where('feature', AiFeature::KnowledgeSearch)->count())->toBe(1);
});

it('falls back to all snippets if the embedding call fails', function () {
    $fake = FakeAiClient::install();
    $workspace = Workspace::factory()->create();
    foreach (range(1, 6) as $index) {
        KnowledgeSnippet::factory()->for($workspace)->embedded([0.5, 0.5])->create(['title' => "Policy {$index}"]);
    }
    $ticket = Ticket::factory()->for($workspace)->awaitingAi()->create();
    $fake->queueFailure(AiFailureReason::Unavailable)->queueText('Draft');

    (new DraftTicketReply($ticket))->handle();

    expect(AiSuggestion::sole()->suggested_value['sources'])->toHaveCount(6)
        ->and($ticket->fresh()->draft_status)->toBe(AiTaskStatus::Completed);
});

it('marks the draft as failed when the AI declines', function () {
    FakeAiClient::install()->withoutEmbeddings()->queueFailure(AiFailureReason::Refused);
    $ticket = Ticket::factory()->awaitingAi()->create();

    (new DraftTicketReply($ticket))->withFakeQueueInteractions()->handle();

    expect($ticket->fresh())
        ->draft_status->toBe(AiTaskStatus::Failed)
        ->draft_error->toBe(AiFailureReason::Refused->userMessage());
});
