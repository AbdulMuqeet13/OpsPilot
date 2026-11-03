<?php

namespace App\Services\AI;

use App\Enums\AiCallStatus;
use App\Enums\AiFeature;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-side numbers for the AI usage page.
 */
class AiUsageReport
{
    public function __construct(private AiSpendGuard $spendGuard) {}

    /**
     * @return array{todayCostUsd: float, last7DaysCostUsd: float, last7DaysCalls: int, last7DaysFailureRate: float, averageDurationMs: int}
     */
    public function summary(Workspace $workspace): array
    {
        $lastWeek = AiUsageLog::query()
            ->whereBelongsTo($workspace)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('count(*) as calls')
            ->selectRaw('coalesce(sum(estimated_cost_usd), 0) as cost')
            ->selectRaw('coalesce(sum(case when status <> ? then 1 else 0 end), 0) as failures', [AiCallStatus::Succeeded->value])
            ->selectRaw('coalesce(avg(duration_ms), 0) as average_duration')
            ->toBase()
            ->first();

        $calls = (int) ($lastWeek->calls ?? 0);

        return [
            'todayCostUsd' => (float) AiUsageLog::query()->whereBelongsTo($workspace)->today()->sum('estimated_cost_usd'),
            'last7DaysCostUsd' => round((float) ($lastWeek->cost ?? 0), 4),
            'last7DaysCalls' => $calls,
            'last7DaysFailureRate' => $calls > 0 ? round((int) $lastWeek->failures / $calls, 3) : 0.0,
            'averageDurationMs' => (int) round((float) ($lastWeek->average_duration ?? 0)),
        ];
    }

    /**
     * @return list<array{feature: string, label: string, calls: int, inputTokens: int, outputTokens: int, costUsd: float}>
     */
    public function byFeature(Workspace $workspace, int $days = 30): array
    {
        return array_values(AiUsageLog::query()
            ->whereBelongsTo($workspace)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('feature')
            ->select('feature')
            ->selectRaw('count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(estimated_cost_usd) as cost')
            ->orderByDesc('cost')
            ->orderBy('feature')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'feature' => (string) $row->feature,
                'label' => AiFeature::tryFrom((string) $row->feature)?->label() ?? (string) $row->feature,
                'calls' => (int) $row->calls,
                'inputTokens' => (int) $row->input_tokens,
                'outputTokens' => (int) $row->output_tokens,
                'costUsd' => round((float) $row->cost, 4),
            ])
            ->all());
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, feature: string, provider: string, model: string, inputTokens: int, outputTokens: int, costUsd: float, durationMs: int, status: string, statusLabel: string, failureMessage: string|null, user: string|null, createdAt: string|null}>
     */
    public function recentCalls(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return AiUsageLog::query()
            ->whereBelongsTo($workspace)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (AiUsageLog $log): array => [
                'id' => $log->id,
                'feature' => $log->feature->label(),
                'provider' => $log->provider,
                'model' => $log->model,
                'inputTokens' => $log->input_tokens,
                'outputTokens' => $log->output_tokens,
                'costUsd' => (float) $log->estimated_cost_usd,
                'durationMs' => $log->duration_ms,
                'status' => $log->status->value,
                'statusLabel' => $log->status->label(),
                'failureMessage' => $log->failure_reason?->userMessage(),
                'user' => $log->user?->name,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);
    }

    /**
     * @return array{capUsd: float, spentTodayUsd: float}|null
     */
    public function demoBudget(): ?array
    {
        if (! $this->spendGuard->isEnforced()) {
            return null;
        }

        return [
            'capUsd' => $this->spendGuard->dailyCapUsd(),
            'spentTodayUsd' => round($this->spendGuard->spentTodayUsd(), 4),
        ];
    }
}
