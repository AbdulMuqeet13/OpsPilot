<?php

namespace App\Actions\Leads;

use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Jobs\DraftLeadFollowUp;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\Workspace;

class CreateLead
{
    /**
     * Store a lead and, unless told otherwise, queue scoring and a follow-up draft.
     *
     * @param  array{name: string, email: string, company?: string|null, job_title?: string|null, company_size?: string|null, budget?: string|null, website?: string|null, message?: string|null}  $input
     */
    public function handle(Workspace $workspace, LeadSource $source, array $input, ?LeadImport $import = null, bool $runAi = true): Lead
    {
        $scoringEnabled = $runAi && $workspace->aiSettingFor(AiFeature::LeadScoring)->is_enabled;
        $followUpEnabled = $runAi && $workspace->aiSettingFor(AiFeature::LeadFollowUp)->is_enabled;
        $hasProfile = filled($workspace->ideal_customer_profile);

        $lead = new Lead;
        $lead->workspace_id = $workspace->id;
        $lead->lead_import_id = $import?->id;
        $lead->source = $source;
        $lead->status = LeadStatus::New;
        $lead->name = $input['name'];
        $lead->email = $input['email'];
        $lead->company = $input['company'] ?? null;
        $lead->job_title = $input['job_title'] ?? null;
        $lead->company_size = $input['company_size'] ?? null;
        $lead->budget = $input['budget'] ?? null;
        $lead->website = $input['website'] ?? null;
        $lead->message = $input['message'] ?? null;
        $lead->scoring_status = $scoringEnabled && $hasProfile ? AiTaskStatus::Pending : AiTaskStatus::Skipped;
        $lead->scoring_error = $scoringEnabled && ! $hasProfile ? 'Add an ideal customer description in Settings → Lead scoring so the AI can score leads.' : null;
        $lead->follow_up_status = $followUpEnabled ? AiTaskStatus::Pending : AiTaskStatus::Skipped;
        $lead->save();

        if ($lead->scoring_status === AiTaskStatus::Pending) {
            ScoreLead::dispatch($lead);
        }

        if ($lead->follow_up_status === AiTaskStatus::Pending) {
            DraftLeadFollowUp::dispatch($lead);
        }

        return $lead;
    }
}
