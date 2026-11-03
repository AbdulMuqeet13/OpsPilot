<?php

namespace App\Http\Controllers\Insights;

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Http\Controllers\Controller;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\Ticket;
use App\Services\Insights\AiActivityReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiActivityController extends Controller
{
    public function index(Request $request, AiActivityReport $report): Response
    {
        $workspace = $request->user()->workspace;
        $days = in_array($request->integer('days'), [7, 30, 90], true) ? $request->integer('days') : 30;
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $feature = AiFeature::tryFrom($request->string('feature')->toString());
        $decision = SuggestionDecision::tryFrom($request->string('decision')->toString());

        $suggestions = $workspace->aiSuggestions()
            ->with(['decider:id,name', 'subject'])
            ->where('created_at', '>=', $from)
            ->where('decision', '!=', SuggestionDecision::Superseded)
            ->when($feature, fn (Builder $query, AiFeature $feature) => $query->where('feature', $feature))
            ->when($decision, fn (Builder $query, SuggestionDecision $decision) => $query->where('decision', $decision))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $selected = $request->filled('selected')
            ? $workspace->aiSuggestions()->with(['usageLog', 'decider:id,name', 'subject'])->find($request->integer('selected'))
            : null;

        return Inertia::render('ai-activity/index', [
            'metrics' => $report->metrics($workspace, $from),
            'suggestions' => $suggestions->through(fn (AiSuggestion $suggestion): array => $this->listItem($suggestion)),
            'selected' => $selected === null ? null : [
                ...$this->listItem($selected),
                'suggested' => $selected->suggested_value,
                'final' => $selected->final_value,
                'call' => $selected->usageLog === null ? null : [
                    'provider' => $selected->usageLog->provider,
                    'model' => $selected->usageLog->model,
                    'inputTokens' => $selected->usageLog->input_tokens,
                    'outputTokens' => $selected->usageLog->output_tokens,
                    'costUsd' => (float) $selected->usageLog->estimated_cost_usd,
                    'durationMs' => $selected->usageLog->duration_ms,
                    'input' => $selected->usageLog->input,
                    'output' => $selected->usageLog->output,
                ],
            ],
            'filters' => [
                'days' => $days,
                'feature' => $feature?->value,
                'decision' => $decision?->value,
            ],
            'features' => array_map(fn (AiFeature $feature): array => ['value' => $feature->value, 'label' => $feature->label()], AiFeature::configurable()),
            'decisions' => array_map(
                fn (SuggestionDecision $decision): array => ['value' => $decision->value, 'label' => $decision->label()],
                array_filter(SuggestionDecision::cases(), fn (SuggestionDecision $decision): bool => $decision !== SuggestionDecision::Superseded),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(AiSuggestion $suggestion): array
    {
        $subject = $suggestion->subject;

        return [
            'id' => $suggestion->id,
            'feature' => $suggestion->feature->label(),
            'field' => $suggestion->field->label(),
            'suggestedLabel' => $suggestion->suggested_value['label'],
            'finalLabel' => $suggestion->final_value['label'] ?? null,
            'decision' => $suggestion->decision->value,
            'decisionLabel' => $suggestion->decision->label(),
            'decidedBy' => $suggestion->decider?->name,
            'decidedAt' => $suggestion->decided_at?->toIso8601String(),
            'createdAt' => $suggestion->created_at?->toIso8601String(),
            'subject' => match (true) {
                $subject instanceof Ticket => ['type' => 'ticket', 'id' => $subject->id, 'label' => "{$subject->reference()} {$subject->subject}"],
                $subject instanceof Lead => ['type' => 'lead', 'id' => $subject->id, 'label' => $subject->company ? "{$subject->name} ({$subject->company})" : $subject->name],
                default => null,
            },
        ];
    }
}
