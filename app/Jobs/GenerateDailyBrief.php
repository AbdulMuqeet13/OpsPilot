<?php

namespace App\Jobs;

use App\Models\DailyBrief;
use App\Services\Insights\DailyBriefWriter;
use Illuminate\Database\Eloquent\Model;

class GenerateDailyBrief extends AiTaskJob
{
    public function __construct(public DailyBrief $brief)
    {
        $this->afterCommit();
    }

    protected function trackedModel(): Model
    {
        return $this->brief;
    }

    protected function task(): string
    {
        return 'generation';
    }

    protected function run(): void
    {
        app(DailyBriefWriter::class)->write($this->brief);
    }
}
