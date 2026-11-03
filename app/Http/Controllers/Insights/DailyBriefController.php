<?php

namespace App\Http\Controllers\Insights;

use App\Actions\Insights\RequestDailyBrief;
use App\Enums\AiFeature;
use App\Enums\BriefTrigger;
use App\Http\Controllers\Controller;
use App\Models\DailyBrief;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyBriefController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $history = $workspace->dailyBriefs()->orderByDesc('brief_date')->limit(14)->get();

        $selected = $request->filled('date')
            ? $history->first(fn (DailyBrief $brief): bool => $brief->brief_date->toDateString() === $request->string('date')->toString())
            : $history->first();

        return Inertia::render('insights/briefs', [
            'brief' => $selected === null ? null : [
                'id' => $selected->id,
                'date' => $selected->brief_date->toDateString(),
                'trigger' => $selected->trigger->label(),
                'status' => $selected->generation_status->value,
                'error' => $selected->generation_error,
                'stats' => $selected->stats,
                'content' => $selected->content,
                'generatedAt' => $selected->generated_at?->toIso8601String(),
                'usedAi' => $selected->ai_usage_log_id !== null,
            ],
            'history' => $history->map(fn (DailyBrief $brief): array => [
                'date' => $brief->brief_date->toDateString(),
                'headline' => $brief->content['headline'] ?? null,
                'status' => $brief->generation_status->value,
            ]),
            'today' => CarbonImmutable::today()->toDateString(),
            'yesterday' => CarbonImmutable::yesterday()->toDateString(),
        ]);
    }

    /**
     * Generate the brief on demand, for yesterday or for today so far.
     */
    public function store(Request $request, AiGateway $gateway, RequestDailyBrief $requestDailyBrief): RedirectResponse
    {
        $validated = $request->validate(['period' => ['required', Rule::in(['yesterday', 'today'])]]);
        $workspace = $request->user()->workspace;

        try {
            $gateway->ensureAvailable($workspace, AiFeature::DailyBrief);
        } catch (AiException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->userMessage()]);

            return back();
        }

        $date = $validated['period'] === 'today' ? CarbonImmutable::today() : CarbonImmutable::yesterday();
        $requestDailyBrief->handle($workspace, $date, BriefTrigger::Manual, $request->user());

        return redirect()->route('daily-briefs.index', ['date' => $date->toDateString()]);
    }
}
