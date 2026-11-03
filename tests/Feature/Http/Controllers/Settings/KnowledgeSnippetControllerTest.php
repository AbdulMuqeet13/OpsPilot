<?php

use App\Jobs\EmbedKnowledgeSnippet;
use App\Models\KnowledgeSnippet;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AiGateway;
use App\Services\AI\Testing\FakeAiClient;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

it('forbids agents', function () {
    actingAs(User::factory()->agent()->create())
        ->post(route('knowledge.store'), ['title' => 'Returns', 'content' => 'Within 30 days.'])
        ->assertForbidden();
});

it('adds a snippet and queues it for embedding', function () {
    Queue::fake([EmbedKnowledgeSnippet::class]);
    $admin = User::factory()->create();

    actingAs($admin)
        ->post(route('knowledge.store'), ['title' => 'Returns', 'content' => 'Unused items can be returned within 30 days.'])
        ->assertSessionHasNoErrors();

    $snippet = KnowledgeSnippet::sole();

    expect($snippet->workspace_id)->toBe($admin->workspace_id);
    Queue::assertPushed(EmbedKnowledgeSnippet::class, fn (EmbedKnowledgeSnippet $job) => $job->snippet->is($snippet));
});

it('clears and refreshes the embedding when the text changes', function () {
    Queue::fake([EmbedKnowledgeSnippet::class]);
    $admin = User::factory()->create();
    $snippet = KnowledgeSnippet::factory()->for($admin->workspace)->embedded([0.1, 0.2])->create();

    actingAs($admin)->patch(route('knowledge.update', $snippet), ['title' => $snippet->title, 'content' => 'Returns within 60 days now.']);

    expect($snippet->fresh()->embedding)->toBeNull();
    Queue::assertPushed(EmbedKnowledgeSnippet::class);
});

it('stores the embedding when the job runs', function () {
    FakeAiClient::install()->queueEmbeddings([[0.3, 0.4]]);
    $snippet = KnowledgeSnippet::factory()->create();

    (new EmbedKnowledgeSnippet($snippet))->handle(app(AiGateway::class));

    expect($snippet->fresh())
        ->embedding->toBe([0.3, 0.4])
        ->embedding_model->toBe('fake-embedding-model');
});

it('limits snippet length', function () {
    actingAs(User::factory()->create())
        ->post(route('knowledge.store'), ['title' => 'Long', 'content' => str_repeat('a', 2001)])
        ->assertSessionHasErrors(['content' => 'The content field must not be greater than 2000 characters.']);
});

it('returns 404 for another workspace\'s snippet', function () {
    $foreign = KnowledgeSnippet::factory()->for(Workspace::factory())->create();

    actingAs(User::factory()->create())->delete(route('knowledge.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});
