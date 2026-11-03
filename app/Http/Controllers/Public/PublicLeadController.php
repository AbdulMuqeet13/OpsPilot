<?php

namespace App\Http\Controllers\Public;

use App\Actions\Leads\CreateLead;
use App\Data\Brand;
use App\Enums\LeadSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePublicLeadRequest;
use App\Models\Workspace;
use App\Support\LeadFormOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicLeadController extends Controller
{
    public function create(Workspace $workspace): Response
    {
        return Inertia::render('public/quote', [
            'brand' => Brand::fromWorkspace($workspace)->toArray(),
            'workspaceSlug' => $workspace->slug,
            'companySizes' => LeadFormOptions::companySizes(),
            'budgets' => LeadFormOptions::budgets(),
        ]);
    }

    public function store(StorePublicLeadRequest $request, Workspace $workspace, CreateLead $createLead): RedirectResponse
    {
        if (! $request->isLikelySpam()) {
            $createLead->handle($workspace, LeadSource::Form, $request->leadInput());
        }

        return Inertia::flash('leadSubmitted', true)->back();
    }
}
