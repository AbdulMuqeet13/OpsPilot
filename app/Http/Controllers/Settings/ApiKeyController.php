<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\RotateWorkspaceApiKey;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/api', [
            'apiKey' => $workspace->api_key_hash === null ? null : [
                'lastFour' => $workspace->api_key_last_four,
                'createdAt' => $workspace->api_key_created_at?->toIso8601String(),
            ],
            'endpoint' => url('/api/v1/tickets'),
            'contactFormUrl' => route('public.tickets.create', $workspace),
        ]);
    }

    public function store(Request $request, RotateWorkspaceApiKey $rotateWorkspaceApiKey): RedirectResponse
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        $plainKey = $rotateWorkspaceApiKey->handle($workspace);

        return Inertia::flash('newApiKey', $plainKey)->back();
    }

    public function destroy(Request $request, RotateWorkspaceApiKey $rotateWorkspaceApiKey): RedirectResponse
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        $rotateWorkspaceApiKey->revoke($workspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'API key revoked. Requests using it will now be refused.']);

        return back();
    }
}
