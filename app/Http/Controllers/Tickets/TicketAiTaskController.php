<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TicketAiTaskController extends Controller
{
    /**
     * Re-run triage or the reply draft for a ticket (for example after a failure).
     */
    public function store(Request $request, Ticket $ticket, string $task, AiGateway $gateway): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $feature = $task === 'triage' ? AiFeature::TicketTriage : AiFeature::ReplyDraft;

        try {
            $gateway->ensureAvailable($ticket->workspace, $feature);
        } catch (AiException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->userMessage()]);

            return back();
        }

        $ticket->forceFill([$task.'_status' => AiTaskStatus::Pending, $task.'_error' => null])->save();

        $task === 'triage' ? TriageTicket::dispatch($ticket) : DraftTicketReply::dispatch($ticket);

        Inertia::flash('toast', ['type' => 'info', 'message' => $task === 'triage' ? 'Re-analysing the ticket…' : 'Drafting a new reply…']);

        return back();
    }
}
