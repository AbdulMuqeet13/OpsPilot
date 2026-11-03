<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

abstract class LeadAiJob extends AiTaskJob
{
    public function __construct(public Lead $lead)
    {
        $this->afterCommit();
    }

    protected function trackedModel(): Model
    {
        return $this->lead;
    }
}
