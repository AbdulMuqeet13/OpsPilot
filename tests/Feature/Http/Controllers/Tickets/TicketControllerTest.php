<?php

use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\AiFeatureSetting;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('index', function () {
    it('redirects guests to sign in', function () {
        get(route('tickets.index'))->assertRedirect(route('login'));
    });

    it('lists only open tickets from the user\'s workspace by default', function () {
        $agent = User::factory()->agent()->create();
        $open = Ticket::factory()->for($agent->workspace)->create(['subject' => 'Open one']);
        Ticket::factory()->for($agent->workspace)->resolved()->create();
        Ticket::factory()->for(Workspace::factory())->create(['subject' => 'Not yours']);

        actingAs($agent)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/index')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $open->id)
                ->where('statusCounts.open', 1)
                ->where('statusCounts.resolved', 1));
    });

    it('shows pending AI suggestions alongside unset fields', function () {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();
        AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Priority, ['value' => 'urgent', 'label' => 'Urgent'])->create();

        actingAs($agent)
            ->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tickets.data.0.priority', null)
                ->where('tickets.data.0.suggested.priority.label', 'Urgent')
                ->where('tickets.data.0.pendingSuggestions', 1));
    });

    it('filters by search term, priority and category', function () {
        $agent = User::factory()->agent()->create();
        $category = TicketCategory::factory()->for($agent->workspace)->create();
        $match = Ticket::factory()->for($agent->workspace)->create(['subject' => 'Wobbly chair leg', 'priority' => TicketPriority::High, 'category_id' => $category->id]);
        Ticket::factory()->for($agent->workspace)->create(['subject' => 'Wobbly table', 'priority' => TicketPriority::Low]);
        Ticket::factory()->for($agent->workspace)->create(['subject' => 'Late delivery', 'priority' => TicketPriority::High]);

        actingAs($agent)
            ->get(route('tickets.index', ['search' => 'wobbly', 'priority' => 'high', 'category' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $match->id));
    });

    it('finds a ticket by its number', function () {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create(['number' => 1234]);
        Ticket::factory()->for($agent->workspace)->create(['number' => 1235]);

        actingAs($agent)
            ->get(route('tickets.index', ['search' => '#1234']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $ticket->id));
    });

    it('sorts the most urgent tickets first when asked', function () {
        $agent = User::factory()->agent()->create();
        Ticket::factory()->for($agent->workspace)->create(['priority' => TicketPriority::Low, 'last_activity_at' => now()]);
        $urgent = Ticket::factory()->for($agent->workspace)->create(['priority' => TicketPriority::Urgent, 'last_activity_at' => now()->subDay()]);

        actingAs($agent)
            ->get(route('tickets.index', ['sort' => 'priority']))
            ->assertInertia(fn (Assert $page) => $page->where('tickets.data.0.id', $urgent->id));
    });
});

describe('show', function () {
    it('shows the conversation and the latest suggestion for each field', function () {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();
        TicketMessage::factory()->for($ticket)->create();
        AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Priority, ['value' => 'low', 'label' => 'Low'])->decided(SuggestionDecision::Superseded)->create();
        $latest = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Priority, ['value' => 'high', 'label' => 'High'])->create();

        actingAs($agent)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/show')
                ->where('ticket.suggestions.priority.id', $latest->id)
                ->has('ticket.messages', 1));
    });

    it('returns 404 for a ticket in another workspace', function () {
        actingAs(User::factory()->create())
            ->get(route('tickets.show', Ticket::factory()->create()))
            ->assertNotFound();
    });
});

describe('update', function () {
    it('lets an agent change status, priority, category and assignee', function () {
        $agent = User::factory()->agent()->create();
        $category = TicketCategory::factory()->for($agent->workspace)->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();

        actingAs($agent)->patch(route('tickets.update', $ticket), [
            'status' => 'resolved',
            'priority' => 'urgent',
            'category_id' => $category->id,
            'assigned_to' => $agent->id,
        ])->assertSessionHasNoErrors();

        expect($ticket->fresh())
            ->status->toBe(TicketStatus::Resolved)
            ->resolved_at->not->toBeNull()
            ->priority->toBe(TicketPriority::Urgent)
            ->category_id->toBe($category->id)
            ->assigned_to->toBe($agent->id);
    });

    it('rejects a category or assignee from another workspace', function () {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create();

        actingAs($agent)->patch(route('tickets.update', $ticket), [
            'category_id' => TicketCategory::factory()->for(Workspace::factory())->create()->id,
            'assigned_to' => User::factory()->create()->id,
        ])->assertSessionHasErrors(['category_id', 'assigned_to']);
    });

    it('returns 404 for a ticket in another workspace', function () {
        actingAs(User::factory()->create())
            ->patch(route('tickets.update', Ticket::factory()->create()), ['status' => 'resolved'])
            ->assertNotFound();
    });
});

describe('re-running AI', function () {
    it('queues triage again and resets its status', function () {
        Queue::fake([TriageTicket::class, DraftTicketReply::class]);
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->for($agent->workspace)->create(['triage_status' => AiTaskStatus::Failed, 'triage_error' => 'Busy']);

        actingAs($agent)->post(route('tickets.ai-tasks.store', [$ticket, 'triage']))->assertRedirect();

        expect($ticket->fresh())->triage_status->toBe(AiTaskStatus::Pending)->triage_error->toBeNull();
        Queue::assertPushed(TriageTicket::class);
        Queue::assertNotPushed(DraftTicketReply::class);
    });

    it('explains why when the feature is switched off', function () {
        Queue::fake([TriageTicket::class, DraftTicketReply::class]);
        $agent = User::factory()->agent()->create();
        AiFeatureSetting::factory()->for($agent->workspace)->disabled()->create(['feature' => AiFeature::ReplyDraft]);
        $ticket = Ticket::factory()->for($agent->workspace)->create();

        actingAs($agent)
            ->post(route('tickets.ai-tasks.store', [$ticket, 'draft']))
            ->assertInertiaFlash('toast.message', AiFailureReason::FeatureDisabled->userMessage());

        Queue::assertNothingPushed();
    });

    it('returns 404 for an unknown task', function () {
        $agent = User::factory()->agent()->create();

        actingAs($agent)
            ->post('/tickets/'.Ticket::factory()->for($agent->workspace)->create()->id.'/ai-tasks/everything')
            ->assertNotFound();
    });
});
