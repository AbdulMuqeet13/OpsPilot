<?php

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Jobs\DraftLeadFollowUp;
use App\Jobs\ScoreLead;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

describe('index', function () {
    it('lists only the workspace\'s leads with score counts', function () {
        $user = User::factory()->agent()->create();
        Lead::factory()->for($user->workspace)->scored(LeadScore::Hot)->create();
        Lead::factory()->for($user->workspace)->scored(LeadScore::Cold)->create();
        Lead::factory()->for($user->workspace)->create();
        Lead::factory()->for(Workspace::factory())->scored(LeadScore::Hot)->create();

        actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('leads/index')
                ->has('leads.data', 3)
                ->where('scoreCounts.hot', 1)
                ->where('scoreCounts.cold', 1)
                ->where('scoreCounts.unscored', 1));
    });

    it('filters by score, including unscored leads', function (string $filter, int $expected) {
        $user = User::factory()->agent()->create();
        Lead::factory()->for($user->workspace)->scored(LeadScore::Hot)->create();
        Lead::factory()->for($user->workspace)->scored(LeadScore::Warm)->create();
        Lead::factory()->for($user->workspace)->create();

        actingAs($user)
            ->get(route('leads.index', ['score' => $filter]))
            ->assertInertia(fn (Assert $page) => $page->has('leads.data', $expected));
    })->with([
        'hot' => ['hot', 1],
        'unscored' => ['unscored', 1],
    ]);

    it('searches by company', function () {
        $user = User::factory()->agent()->create();
        $match = Lead::factory()->for($user->workspace)->create(['company' => 'Brightspace Offices']);
        Lead::factory()->for($user->workspace)->create(['company' => 'Northwind Interiors']);

        actingAs($user)
            ->get(route('leads.index', ['search' => 'brightspace']))
            ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('leads.data.0.id', $match->id));
    });

    it('shows a pending AI score next to an unscored lead', function () {
        $user = User::factory()->agent()->create();
        $lead = Lead::factory()->for($user->workspace)->create();
        AiSuggestion::factory()->forSubject($lead)->field(SuggestionField::LeadScore, ['value' => 'warm', 'label' => 'Warm', 'reason' => 'Unclear budget.'])->create();

        actingAs($user)
            ->get(route('leads.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.data.0.score', null)
                ->where('leads.data.0.suggestedScore.label', 'Warm'));
    });
});

describe('show and update', function () {
    it('returns 404 for a lead in another workspace', function () {
        actingAs(User::factory()->create())->get(route('leads.show', Lead::factory()->create()))->assertNotFound();
        actingAs(User::factory()->create())->patch(route('leads.update', Lead::factory()->create()), ['status' => 'qualified'])->assertNotFound();
    });

    it('updates the status', function () {
        $user = User::factory()->agent()->create();
        $lead = Lead::factory()->for($user->workspace)->create();

        actingAs($user)->patch(route('leads.update', $lead), ['status' => 'qualified'])->assertSessionHasNoErrors();

        expect($lead->fresh()->status)->toBe(LeadStatus::Qualified);
    });
});

describe('score decisions', function () {
    it('applies an accepted score and its reason', function () {
        $user = User::factory()->agent()->create();
        $lead = Lead::factory()->for($user->workspace)->create();
        $suggestion = AiSuggestion::factory()->forSubject($lead)->field(SuggestionField::LeadScore, ['value' => 'hot', 'label' => 'Hot', 'reason' => '40-room hotel next quarter.'])->create();

        actingAs($user)->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'accept']);

        expect($lead->fresh())
            ->score->toBe(LeadScore::Hot)
            ->score_reason->toBe('40-room hotel next quarter.');
    });

    it('keeps the AI\'s reasoning visible when someone overrides the score', function () {
        $user = User::factory()->agent()->create();
        $lead = Lead::factory()->for($user->workspace)->create();
        $suggestion = AiSuggestion::factory()->forSubject($lead)->field(SuggestionField::LeadScore, ['value' => 'cold', 'label' => 'Cold', 'reason' => 'No budget given.'])->create();

        actingAs($user)->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'edit', 'value' => 'warm']);

        expect($lead->fresh())
            ->score->toBe(LeadScore::Warm)
            ->score_reason->toBe('AI suggested Cold: No budget given.')
            ->and($suggestion->fresh()->decision)->toBe(SuggestionDecision::Edited);
    });

    it('rejects an invalid score', function () {
        $user = User::factory()->agent()->create();
        $suggestion = AiSuggestion::factory()->forSubject(Lead::factory()->for($user->workspace)->create())->field(SuggestionField::LeadScore, ['value' => 'cold', 'label' => 'Cold'])->create();

        actingAs($user)
            ->post(route('ai-suggestions.decision.store', $suggestion), ['decision' => 'edit', 'value' => 'scorching'])
            ->assertSessionHasErrors(['value' => 'Choose hot, warm or cold.']);
    });
});

describe('re-running AI', function () {
    it('queues a fresh score', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $user = User::factory()->agent()->for(Workspace::factory()->state(['ideal_customer_profile' => 'Hotels.']))->create();
        $lead = Lead::factory()->for($user->workspace)->create();

        actingAs($user)->post(route('leads.ai-tasks.store', [$lead, 'scoring']))->assertRedirect();

        Queue::assertPushed(ScoreLead::class);
    });

    it('explains that scoring needs an ideal customer description', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $user = User::factory()->agent()->create();
        $lead = Lead::factory()->for($user->workspace)->create();

        actingAs($user)
            ->post(route('leads.ai-tasks.store', [$lead, 'scoring']))
            ->assertInertiaFlash('toast.message', 'Add an ideal customer description in Settings → Lead scoring first.');

        Queue::assertNothingPushed();
    });
});
