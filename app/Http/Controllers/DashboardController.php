<?php

namespace App\Http\Controllers;

use App\Services\Insights\DashboardSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary): Response
    {
        $workspace = $request->user()->workspace;

        return Inertia::render('dashboard', [
            'stats' => $summary->stats($workspace),
            'attention' => $summary->needsAttention($workspace),
            'latestBrief' => $summary->latestBrief($workspace),
            'aiSpendToday' => $request->user()->isAdmin() ? $summary->aiSpendToday($workspace) : null,
        ]);
    }
}
