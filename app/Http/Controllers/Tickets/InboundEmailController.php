<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreInboundEmailRequest;
use App\Services\Tickets\InboundEmailParser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InboundEmailController extends Controller
{
    /**
     * Simulates an inbound mailbox: paste a raw email and it becomes a ticket.
     */
    public function create(): Response
    {
        return Inertia::render('tickets/inbound-email');
    }

    public function store(StoreInboundEmailRequest $request, InboundEmailParser $parser, CreateTicket $createTicket): RedirectResponse
    {
        $ticket = $createTicket->handle(
            $request->user()->workspace,
            TicketSource::Email,
            $parser->parse($request->string('raw_email')->toString()),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Ticket {$ticket->reference()} created from the email. AI is analysing it now."]);

        return redirect()->route('tickets.show', $ticket);
    }
}
