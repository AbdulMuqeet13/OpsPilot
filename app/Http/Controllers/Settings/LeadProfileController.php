<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLeadProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/lead-scoring', [
            'profile' => [
                'ideal_customer_profile' => $workspace->ideal_customer_profile,
                'offer_summary' => $workspace->offer_summary,
            ],
            'leadFormUrl' => route('public.leads.create', $workspace),
        ]);
    }

    public function update(UpdateLeadProfileRequest $request): RedirectResponse
    {
        $request->user()->workspace->update([
            'ideal_customer_profile' => $request->filled('ideal_customer_profile') ? $request->string('ideal_customer_profile')->trim()->toString() : null,
            'offer_summary' => $request->filled('offer_summary') ? $request->string('offer_summary')->trim()->toString() : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved. New leads will be scored against this description.']);

        return back();
    }
}
