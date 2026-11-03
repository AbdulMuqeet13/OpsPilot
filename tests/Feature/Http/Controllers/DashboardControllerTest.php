<?php

use App\Enums\LeadScore;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('shows the workspace\'s headline numbers', function () {
    $user = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($user->workspace)->create();
    Ticket::factory()->for($user->workspace)->resolved()->create();
    Lead::factory()->for($user->workspace)->scored(LeadScore::Hot)->create();
    AiSuggestion::factory()->forTicket($ticket)->create();
    AiSuggestion::factory()->forTicket($ticket)->decided(SuggestionDecision::Accepted)->create();
    AiSuggestion::factory()->forTicket($ticket)->decided(SuggestionDecision::Rejected)->create();
    Ticket::factory()->for(Workspace::factory())->count(3)->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.openTickets', 1)
            ->where('stats.awaitingReview', 1)
            ->where('stats.hotLeadsToContact', 1)
            ->where('stats.acceptanceRate', 0.5)
            ->where('aiSpendToday', null));
});

it('flags urgent tickets, including ones only the AI has marked urgent so far', function () {
    $user = User::factory()->agent()->create();
    $urgent = Ticket::factory()->for($user->workspace)->create(['priority' => TicketPriority::Urgent]);
    $aiFlagged = Ticket::factory()->for($user->workspace)->create(['priority' => null]);
    AiSuggestion::factory()->forTicket($aiFlagged)->field(SuggestionField::Priority, ['value' => 'urgent', 'label' => 'Urgent'])->create();
    Ticket::factory()->for($user->workspace)->create(['priority' => TicketPriority::Low]);
    $hotLead = Lead::factory()->for($user->workspace)->scored(LeadScore::Hot)->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 3)
            ->where('attention.0.id', $urgent->id)
            ->where('attention.1.id', $aiFlagged->id)
            ->where('attention.1.badge', 'AI: high priority')
            ->where('attention.2.id', $hotLead->id));
});

it('shows today\'s AI spend to admins', function () {
    $admin = User::factory()->create();
    AiUsageLog::factory()->for($admin->workspace)->costing(0.0123)->create();

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('aiSpendToday', 0.0123));
});
