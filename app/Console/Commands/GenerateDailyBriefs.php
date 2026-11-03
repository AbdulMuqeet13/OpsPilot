<?php

namespace App\Console\Commands;

use App\Actions\Insights\RequestDailyBrief;
use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\BriefTrigger;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('briefs:generate {--date= : The day to summarise (Y-m-d). Defaults to yesterday.}')]
#[Description('Queue the Daily Brief for every active workspace')]
class GenerateDailyBriefs extends Command
{
    public function handle(RequestDailyBrief $requestDailyBrief): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::yesterday();

        $queued = 0;

        Workspace::query()
            ->where(fn (Builder $query) => $query->where('is_sandbox', false)->orWhere('expires_at', '>', now()))
            ->with('aiFeatureSettings')
            ->lazyById()
            ->each(function (Workspace $workspace) use ($date, $requestDailyBrief, &$queued): void {
                $alreadyDone = $workspace->dailyBriefs()
                    ->whereDate('brief_date', $date)
                    ->where('generation_status', AiTaskStatus::Completed)
                    ->exists();

                if ($alreadyDone || ! $workspace->aiSettingFor(AiFeature::DailyBrief)->is_enabled) {
                    return;
                }

                $requestDailyBrief->handle($workspace, $date, BriefTrigger::Scheduled);
                $queued++;
            });

        $this->info("Queued {$queued} daily briefs for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
