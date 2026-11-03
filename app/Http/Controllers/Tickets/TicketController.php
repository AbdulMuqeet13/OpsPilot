<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\UpdateTicketDetails;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketDetailResource;
use App\Http\Resources\TicketListItemResource;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Tickets\TicketListQuery;
use App\Support\Languages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request, TicketListQuery $listQuery): Response
    {
        $workspace = $request->user()->workspace;
        $filters = [
            'status' => $request->string('status', TicketStatus::Open->value)->toString(),
            'priority' => $request->string('priority')->toString() ?: null,
            'category' => $request->integer('category') ?: null,
            'search' => $request->string('search')->trim()->limit(100, '')->toString() ?: null,
            'sort' => $request->string('sort')->toString() === 'priority' ? 'priority' : 'recent',
        ];

        $tickets = $listQuery->paginate($workspace, $filters);

        return Inertia::render('tickets/index', [
            'tickets' => $tickets->through(fn (Ticket $ticket): array => (new TicketListItemResource($ticket))->resolve($request)),
            'filters' => $filters,
            'statusCounts' => $workspace->tickets()
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $total): int => (int) $total),
            'categories' => $workspace->ticketCategories->map(fn (TicketCategory $category): array => $category->toBadgeArray()),
            'priorities' => array_map(fn (TicketPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()], TicketPriority::cases()),
            'contactFormUrl' => route('public.tickets.create', $workspace),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['category', 'messages.user:id,name', 'aiSuggestions.decider:id,name']);
        $workspace = $request->user()->workspace;

        return Inertia::render('tickets/show', [
            'ticket' => (new TicketDetailResource($ticket))->resolve($request),
            'categories' => $workspace->ticketCategories->map(fn (TicketCategory $category): array => $category->toBadgeArray()),
            'agents' => $workspace->users()->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name]),
            'priorities' => array_map(fn (TicketPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()], TicketPriority::cases()),
            'statuses' => array_map(fn (TicketStatus $status): array => ['value' => $status->value, 'label' => $status->label()], TicketStatus::cases()),
            'languages' => Languages::options(),
            'demoMode' => (bool) config('demo.enabled'),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, UpdateTicketDetails $updateTicketDetails): RedirectResponse
    {
        $updateTicketDetails->handle($ticket, $request->changes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ticket updated.']);

        return back();
    }
}
