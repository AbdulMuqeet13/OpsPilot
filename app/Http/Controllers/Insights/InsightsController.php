<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Services\Insights\InsightsReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function index(Request $request, InsightsReport $report): Response
    {
        $workspace = $request->user()->workspace;
        $days = in_array($request->integer('days'), InsightsReport::RANGES, true) ? $request->integer('days') : InsightsReport::DEFAULT_RANGE;
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        return Inertia::render('insights/index', [
            'days' => $days,
            'ranges' => InsightsReport::RANGES,
            'summary' => $report->summary($workspace, $from),
            'ticketsByCategory' => $report->ticketsByCategory($workspace, $from),
            'dailyTicketVolume' => $report->dailyTicketVolume($workspace, $from),
            'dailySentiment' => $report->dailySentiment($workspace, $from),
            'leadScores' => $report->leadScoreDistribution($workspace, $from),
        ]);
    }
}
