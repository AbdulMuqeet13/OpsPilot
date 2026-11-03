<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Ai\DecideAiSuggestion;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Http\Controllers\Controller;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketTriageAcceptanceController extends Controller
{
    /**
     * Accept every pending triage suggestion on the ticket in one click.
     */
    public function store(Request $request, Ticket $ticket, DecideAiSuggestion $decideAiSuggestion): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $pending = $ticket->aiSuggestions()
            ->pending()
            ->whereIn('field', SuggestionField::ticketTriageFields())
            ->get();

        $pending->each(fn (AiSuggestion $suggestion) => $decideAiSuggestion->handle($suggestion, $request->user(), SuggestionDecision::Accepted));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Accepted {$pending->count()} AI suggestions."]);

        return back();
    }
}
