<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\User;
use App\Support\DraftComparison;
use Illuminate\Support\Facades\DB;

class MarkFollowUpSent
{
    /**
     * Record the follow-up the salesperson sent from their own mailbox, and what happened to the AI draft.
     */
    public function handle(Lead $lead, User $user, string $subject, string $body): Lead
    {
        return DB::transaction(function () use ($lead, $user, $subject, $body): Lead {
            $draft = $lead->aiSuggestions()
                ->pending()
                ->where('field', SuggestionField::FollowUp)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($draft instanceof AiSuggestion) {
                $decision = DraftComparison::decisionFor(
                    $draft->suggested_value['label']."\n".$draft->suggested_value['value'],
                    $subject."\n".$body,
                );

                $draft->forceFill([
                    'decision' => $decision,
                    'final_value' => $decision === SuggestionDecision::Rejected ? null : ['value' => $body, 'label' => $subject, 'subject' => $subject],
                    'decided_by' => $user->id,
                    'decided_at' => now(),
                ])->save();
            }

            $lead->follow_up_subject = $subject;
            $lead->follow_up_body = $body;
            $lead->follow_up_sent_at = now();
            $lead->follow_up_sent_by = $user->id;

            if ($lead->status === LeadStatus::New) {
                $lead->status = LeadStatus::Contacted;
            }

            $lead->save();

            return $lead;
        });
    }
}
