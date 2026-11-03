<?php

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiFeatureSetting;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Testing\FakeAiClient;

use function Pest\Laravel\actingAs;

describe('reply drafts', function () {
    it('streams a fresh draft and stores it, replacing the pending one', function () {
        FakeAiClient::install()->withoutEmbeddings()->queueText('Hi Priya, a new cushion is on its way.');
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();
        $oldDraft = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => 'Old', 'label' => 'Old'])->create();

        $content = actingAs($agent)->post(route('tickets.reply-drafts.store', $ticket))->assertOk()->streamedContent();

        $newDraft = AiSuggestion::where('field', SuggestionField::Reply)->pending()->sole();

        expect($content)
            ->toContain('event: delta')
            ->toContain('"suggestion":{"id":'.$newDraft->id)
            ->and($newDraft->suggested_value['value'])->toBe('Hi Priya, a new cushion is on its way.')
            ->and($oldDraft->fresh()->decision)->toBe(SuggestionDecision::Superseded)
            ->and(AiUsageLog::sole()->user_id)->toBe($agent->id);
    });

    it('returns 503 when reply drafts are switched off', function () {
        $fake = FakeAiClient::install();
        $agent = User::factory()->agent()->create();
        AiFeatureSetting::factory()->for($agent->workspace)->disabled()->create(['feature' => AiFeature::ReplyDraft]);

        actingAs($agent)
            ->postJson(route('tickets.reply-drafts.store', Ticket::factory()->for($agent->workspace)->create()))
            ->assertStatus(503);

        $fake->assertNothingSent();
    });
});

describe('reply rewrites', function () {
    it('streams the rewritten reply', function () {
        $fake = FakeAiClient::install()->queueText('Hola Priya, ya va en camino un cojín nuevo.');
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();

        $content = actingAs($agent)
            ->post(route('tickets.reply-rewrites.store', $ticket), ['text' => 'Hi Priya, a new cushion is on its way.', 'style' => 'translate', 'language' => 'es'])
            ->streamedContent();

        expect($content)->toContain('Hola Priya, ya va en camino un cojín nuevo.')
            ->and(AiUsageLog::sole()->feature)->toBe(AiFeature::ReplyRewrite)
            ->and(AiSuggestion::count())->toBe(0);

        $fake->assertSent(fn (ChatRequest $request) => str_contains($request->system, 'Translate it into Spanish')
            && str_contains($request->messages[0]['content'], '<reply>'));
    });

    it('requires a target language for translations', function () {
        $agent = User::factory()->agent()->create();

        actingAs($agent)
            ->postJson(route('tickets.reply-rewrites.store', Ticket::factory()->for($agent->workspace)->create()), ['text' => 'Hi', 'style' => 'translate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('language');
    });

    it('rejects unknown styles', function () {
        $agent = User::factory()->agent()->create();

        actingAs($agent)
            ->postJson(route('tickets.reply-rewrites.store', Ticket::factory()->for($agent->workspace)->create()), ['text' => 'Hi', 'style' => 'pirate'])
            ->assertJsonValidationErrors('style');
    });

    it('returns 404 for a ticket in another workspace', function () {
        actingAs(User::factory()->create())
            ->postJson(route('tickets.reply-rewrites.store', Ticket::factory()->create()), ['text' => 'Hi', 'style' => 'shorter'])
            ->assertNotFound();
    });
});
