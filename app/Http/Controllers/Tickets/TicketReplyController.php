<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\SendTicketReply;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketReplyRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketReplyController extends Controller
{
    public function store(StoreTicketReplyRequest $request, Ticket $ticket, SendTicketReply $sendTicketReply): RedirectResponse
    {
        $message = $sendTicketReply->handle(
            $ticket,
            $request->user(),
            $request->string('body')->trim()->toString(),
            $request->boolean('resolve'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message->was_emailed
                ? "Reply sent to {$ticket->requester_name}."
                : 'Reply saved to the conversation. (Demo mode never emails real customers.)',
        ]);

        return back();
    }
}
