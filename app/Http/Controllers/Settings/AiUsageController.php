<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AI\AiUsageReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiUsageController extends Controller
{
    public function index(Request $request, AiUsageReport $report): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/ai-usage', [
            'summary' => $report->summary($workspace),
            'byFeature' => $report->byFeature($workspace),
            'recentCalls' => $report->recentCalls($workspace),
            'demoBudget' => $report->demoBudget(),
        ]);
    }
}
