<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\MarkFollowUpSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\MarkFollowUpSentRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadFollowUpController extends Controller
{
    /**
     * The salesperson sends the email from their own mailbox; this records it as sent.
     */
    public function store(MarkFollowUpSentRequest $request, Lead $lead, MarkFollowUpSent $markFollowUpSent): RedirectResponse
    {
        $markFollowUpSent->handle(
            $lead,
            $request->user(),
            $request->string('subject')->trim()->toString(),
            $request->string('body')->trim()->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Follow-up to {$lead->name} marked as sent."]);

        return back();
    }
}
