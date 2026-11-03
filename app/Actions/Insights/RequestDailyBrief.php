<?php

namespace App\Actions\Insights;

use App\Enums\AiTaskStatus;
use App\Enums\BriefTrigger;
use App\Jobs\GenerateDailyBrief;
use App\Models\DailyBrief;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class RequestDailyBrief
{
    /**
     * Create (or reset) the brief for a day and queue its generation.
     */
    public function handle(Workspace $workspace, CarbonImmutable $date, BriefTrigger $trigger, ?User $requester = null): DailyBrief
    {
        $brief = DailyBrief::query()
            ->whereBelongsTo($workspace)
            ->whereDate('brief_date', $date)
            ->first() ?? new DailyBrief;

        $brief->forceFill([
            'workspace_id' => $workspace->id,
            'brief_date' => $date->toDateString(),
            'trigger' => $trigger,
            'requested_by' => $requester?->id,
            'generation_status' => AiTaskStatus::Pending,
            'generation_error' => null,
        ])->save();

        GenerateDailyBrief::dispatch($brief);

        return $brief;
    }
}
