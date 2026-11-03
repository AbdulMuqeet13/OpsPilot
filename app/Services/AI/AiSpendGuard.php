<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Cache;

/**
 * The demo's global daily AI budget, measured from ai_usage_logs.
 */
class AiSpendGuard
{
    public function isEnforced(): bool
    {
        return config('demo.enabled') && $this->dailyCapUsd() > 0;
    }

    public function dailyCapUsd(): float
    {
        return (float) config('demo.daily_ai_spend_cap_usd');
    }

    public function spentTodayUsd(): float
    {
        return (float) AiUsageLog::query()->today()->sum('estimated_cost_usd');
    }

    public function capReached(): bool
    {
        return $this->isEnforced() && $this->spentTodayUsd() >= $this->dailyCapUsd();
    }

    /**
     * A slightly stale answer for page chrome, so every page view doesn't run the sum query.
     */
    public function capReachedCached(): bool
    {
        if (! $this->isEnforced()) {
            return false;
        }

        return Cache::remember('ai:spend-cap-reached', now()->addSeconds(30), fn (): bool => $this->capReached());
    }
}
