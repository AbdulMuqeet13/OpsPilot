<?php

use App\Enums\LeadScore;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('summarises the user\'s workspace for the chosen range', function () {
    $this->travelTo(now()->setTime(12, 0));
    $user = User::factory()->agent()->create();
    $delivery = TicketCategory::factory()->for($user->workspace)->create(['name' => 'Delivery delays']);
    Ticket::factory()->for($user->workspace)->count(2)->create(['category_id' => $delivery->id, 'sentiment_score' => -0.6, 'created_at' => now()->subDays(2)]);
    Ticket::factory()->for($user->workspace)->create(['category_id' => null, 'sentiment_score' => 0.4, 'created_at' => now()]);
    Ticket::factory()->for($user->workspace)->create(['created_at' => now()->subDays(20)]);
    Ticket::factory()->for(Workspace::factory())->count(5)->create();
    Lead::factory()->for($user->workspace)->scored(LeadScore::Hot)->create();
    Lead::factory()->for($user->workspace)->scored(LeadScore::Cold)->create();
    Lead::factory()->for($user->workspace)->create();

    actingAs($user)
        ->get(route('insights.index', ['days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('insights/index')
            ->where('days', 7)
            ->where('summary.tickets', 3)
            ->where('summary.averageSentiment', -0.27)
            ->where('summary.hotLeadShare', 0.5)
            ->where('ticketsByCategory', [['label' => 'Delivery delays', 'value' => 2], ['label' => 'Uncategorised', 'value' => 1]])
            ->has('dailyTicketVolume', 7)
            ->where('dailyTicketVolume.4.value', 2)
            ->where('dailyTicketVolume.5.value', 0)
            ->where('dailySentiment.4.value', -0.6)
            ->where('dailySentiment.5.value', null)
            ->where('leadScores', [
                ['key' => 'hot', 'label' => 'Hot', 'value' => 1],
                ['key' => 'warm', 'label' => 'Warm', 'value' => 0],
                ['key' => 'cold', 'label' => 'Cold', 'value' => 1],
                ['key' => 'unscored', 'label' => 'Not scored', 'value' => 1],
            ]));
});

it('falls back to 14 days for an unsupported range', function () {
    actingAs(User::factory()->create())
        ->get(route('insights.index', ['days' => 365]))
        ->assertInertia(fn (Assert $page) => $page->where('days', 14)->has('dailyTicketVolume', 14));
});

it('shows tickets whose AI category is unreviewed as awaiting review', function () {
    $user = User::factory()->agent()->create();
    $unreviewed = Ticket::factory()->for($user->workspace)->create(['category_id' => null]);
    AiSuggestion::factory()->forTicket($unreviewed)->field(SuggestionField::Category, ['value' => 1, 'label' => 'Refunds'])->create();
    Ticket::factory()->for($user->workspace)->create(['category_id' => null]);

    actingAs($user)
        ->get(route('insights.index'))
        ->assertInertia(fn (Assert $page) => $page->where('ticketsByCategory', [
            ['label' => 'Awaiting review', 'value' => 1],
            ['label' => 'Uncategorised', 'value' => 1],
        ]));
});

it('shows empty charts gracefully', function () {
    actingAs(User::factory()->create())
        ->get(route('insights.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.averageSentiment', null)
            ->where('summary.hotLeadShare', null)
            ->where('ticketsByCategory', []));
});
