<?php

namespace App\Services\Insights;

use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

/**
 * How often people accept, edit or reject the AI's suggestions.
 *
 * Acceptance rate = accepted ÷ (accepted + edited + rejected). Auto-applied
 * and superseded suggestions are excluded because no person judged them.
 */
class AiActivityReport
{
    /**
     * @return array{decided: int, accepted: int, edited: int, rejected: int, pending: int, autoApplied: int, acceptanceRate: float|null, approvalRate: float|null, byFeature: list<array{feature: string, label: string, decided: int, accepted: int, edited: int, rejected: int, acceptanceRate: float|null}>}
     */
    public function metrics(Workspace $workspace, CarbonImmutable $from): array
    {
        $rows = $workspace->aiSuggestions()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->selectRaw('feature, decision, count(*) as total')
            ->groupBy('feature', 'decision')
            ->get();

        $count = fn (?string $feature, SuggestionDecision $decision): int => (int) $rows
            ->when($feature !== null, fn ($collection) => $collection->where('feature', $feature))
            ->where('decision', $decision->value)
            ->sum('total');

        $rate = fn (int $part, int $whole): ?float => $whole > 0 ? round($part / $whole, 3) : null;

        $accepted = $count(null, SuggestionDecision::Accepted);
        $edited = $count(null, SuggestionDecision::Edited);
        $rejected = $count(null, SuggestionDecision::Rejected);
        $decided = $accepted + $edited + $rejected;

        $byFeature = [];

        foreach (AiFeature::configurable() as $feature) {
            $featureAccepted = $count($feature->value, SuggestionDecision::Accepted);
            $featureEdited = $count($feature->value, SuggestionDecision::Edited);
            $featureRejected = $count($feature->value, SuggestionDecision::Rejected);
            $featureDecided = $featureAccepted + $featureEdited + $featureRejected;

            // Rewrites and briefs are never accepted or rejected, so they have no rate to show.
            if (in_array($feature, [AiFeature::ReplyRewrite, AiFeature::DailyBrief], true)) {
                continue;
            }

            $byFeature[] = [
                'feature' => $feature->value,
                'label' => $feature->label(),
                'decided' => $featureDecided,
                'accepted' => $featureAccepted,
                'edited' => $featureEdited,
                'rejected' => $featureRejected,
                'acceptanceRate' => $rate($featureAccepted, $featureDecided),
            ];
        }

        return [
            'decided' => $decided,
            'accepted' => $accepted,
            'edited' => $edited,
            'rejected' => $rejected,
            'pending' => $count(null, SuggestionDecision::Pending),
            'autoApplied' => $count(null, SuggestionDecision::AutoApplied),
            'acceptanceRate' => $rate($accepted, $decided),
            'approvalRate' => $rate($accepted + $edited, $decided),
            'byFeature' => $byFeature,
        ];
    }
}
