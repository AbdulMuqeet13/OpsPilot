<?php

namespace App\Http\Controllers\Leads;

use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DraftLeadFollowUp;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LeadAiTaskController extends Controller
{
    /**
     * Re-score the lead or draft a fresh follow-up.
     */
    public function store(Lead $lead, string $task, AiGateway $gateway): RedirectResponse
    {
        Gate::authorize('update', $lead);

        if ($task === 'scoring' && blank($lead->workspace->ideal_customer_profile)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Add an ideal customer description in Settings → Lead scoring first.']);

            return back();
        }

        try {
            $gateway->ensureAvailable($lead->workspace, $task === 'scoring' ? AiFeature::LeadScoring : AiFeature::LeadFollowUp);
        } catch (AiException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->userMessage()]);

            return back();
        }

        $lead->forceFill([$task.'_status' => AiTaskStatus::Pending, $task.'_error' => null])->save();

        $task === 'scoring' ? ScoreLead::dispatch($lead) : DraftLeadFollowUp::dispatch($lead);

        Inertia::flash('toast', ['type' => 'info', 'message' => $task === 'scoring' ? 'Re-scoring this lead…' : 'Drafting a new follow-up…']);

        return back();
    }
}
