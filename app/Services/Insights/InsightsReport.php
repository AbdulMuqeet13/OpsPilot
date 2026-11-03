<?php

namespace App\Services\Insights;

use App\Enums\LeadScore;
use App\Enums\SuggestionField;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Chart data for the Insights page. All numbers come straight from the database.
 */
class InsightsReport
{
    public const array RANGES = [7, 14, 30, 90];

    public const int DEFAULT_RANGE = 14;

    /**
     * @return array{tickets: int, leads: int, averageSentiment: float|null, hotLeadShare: float|null}
     */
    public function summary(Workspace $workspace, CarbonImmutable $from): array
    {
        $tickets = $workspace->tickets()->where('created_at', '>=', $from);
        $leads = $workspace->leads()->where('created_at', '>=', $from);
        $leadCount = (clone $leads)->count();
        $scoredLeads = (clone $leads)->whereNotNull('score')->count();
        $average = (clone $tickets)->whereNotNull('sentiment_score')->avg('sentiment_score');

        return [
            'tickets' => (clone $tickets)->count(),
            'leads' => $leadCount,
            'averageSentiment' => $average === null ? null : round((float) $average, 2),
            'hotLeadShare' => $scoredLeads > 0 ? round((clone $leads)->where('score', LeadScore::Hot)->count() / $scoredLeads, 3) : null,
        ];
    }

    /**
     * Reviewed categories only. Tickets whose AI category nobody has reviewed yet are
     * shown as "Awaiting review" rather than being counted under the AI's guess.
     *
     * @return list<array{label: string, value: int}>
     */
    public function ticketsByCategory(Workspace $workspace, CarbonImmutable $from): array
    {
        $names = $workspace->ticketCategories()->pluck('name', 'id');

        $rows = $workspace->tickets()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn (object $row): array => ['label' => (string) ($names[$row->category_id] ?? 'Uncategorised'), 'value' => (int) $row->total])
            ->sortByDesc('value');

        $withoutCategory = $workspace->tickets()->where('created_at', '>=', $from)->whereNull('category_id');
        $awaitingReview = (clone $withoutCategory)
            ->whereHas('aiSuggestions', fn ($suggestions) => $suggestions->pending()->where('field', SuggestionField::Category))
            ->count();
        $uncategorised = (clone $withoutCategory)->count() - $awaitingReview;

        if ($awaitingReview > 0) {
            $rows->push(['label' => 'Awaiting review', 'value' => $awaitingReview]);
        }

        if ($uncategorised > 0) {
            $rows->push(['label' => 'Uncategorised', 'value' => $uncategorised]);
        }

        return array_values($rows->all());
    }

    /**
     * Tickets per day, with zero-filled gaps so the axis is continuous.
     *
     * @return list<array{date: string, value: int}>
     */
    public function dailyTicketVolume(Workspace $workspace, CarbonImmutable $from): array
    {
        $counts = $workspace->tickets()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->eachDay($from, fn (string $day): array => ['date' => $day, 'value' => (int) ($counts[$day] ?? 0)]);
    }

    /**
     * Average sentiment per day; days without triaged tickets are null (a gap, not a zero).
     *
     * @return list<array{date: string, value: float|null, tickets: int}>
     */
    public function dailySentiment(Workspace $workspace, CarbonImmutable $from): array
    {
        $rows = $workspace->tickets()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->whereNotNull('sentiment_score')
            ->selectRaw('date(created_at) as day, avg(sentiment_score) as average, count(*) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return $this->eachDay($from, fn (string $day): array => [
            'date' => $day,
            'value' => isset($rows[$day]) ? round((float) $rows[$day]->average, 2) : null,
            'tickets' => isset($rows[$day]) ? (int) $rows[$day]->total : 0,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, value: int}>
     */
    public function leadScoreDistribution(Workspace $workspace, CarbonImmutable $from): array
    {
        $counts = $workspace->leads()
            ->toBase()
            ->where('created_at', '>=', $from)
            ->selectRaw("coalesce(score, 'unscored') as bucket, count(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $buckets = [...array_map(fn (LeadScore $score): array => [$score->value, $score->label()], LeadScore::cases()), ['unscored', 'Not scored']];

        return array_map(fn (array $bucket): array => ['key' => $bucket[0], 'label' => $bucket[1], 'value' => (int) ($counts[$bucket[0]] ?? 0)], $buckets);
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $row
     * @return list<T>
     */
    private function eachDay(CarbonImmutable $from, callable $row): array
    {
        $days = [];

        foreach (CarbonPeriod::create($from->startOfDay(), now()->startOfDay()) as $day) {
            $days[] = $row($day->toDateString());
        }

        return $days;
    }
}
