<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/branding', [
            'branding' => [
                'brand_name' => $workspace->brand_name,
                'primary_color' => $workspace->primary_color,
                'support_email' => $workspace->support_email,
            ],
            'maxLogoKilobytes' => config('opspilot.logo.max_kilobytes'),
        ]);
    }

    public function update(UpdateBrandingRequest $request, UpdateBranding $updateBranding): RedirectResponse
    {
        $updateBranding->handle(
            $request->user()->workspace,
            $request->brandingAttributes(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branding saved. The whole app now uses it.']);

        return back();
    }
}
