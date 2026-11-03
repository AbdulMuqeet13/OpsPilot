<?php

namespace App\Services\Insights;

use App\Enums\AiTaskStatus;
use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Enums\SuggestionDecision;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AiUsageLog;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class DashboardSummary
{
    public function __construct(private AiActivityReport $activity) {}

    /**
     * @return array{openTickets: int, awaitingReview: int, hotLeadsToContact: int, acceptanceRate: float|null, decided: int}
     */
    public function stats(Workspace $workspace): array
    {
        $metrics = $this->activity->metrics($workspace, CarbonImmutable::now()->subDays(29)->startOfDay());

        return [
            'openTickets' => $workspace->tickets()->where('status', TicketStatus::Open)->count(),
            'awaitingReview' => $workspace->aiSuggestions()->where('decision', SuggestionDecision::Pending)->count(),
            'hotLeadsToContact' => $workspace->leads()->where('score', LeadScore::Hot)->where('status', LeadStatus::New)->count(),
            'acceptanceRate' => $metrics['acceptanceRate'],
            'decided' => $metrics['decided'],
        ];
    }

    /**
     * Urgent or high-priority open tickets (including AI-suggested priority) and hot leads nobody has contacted.
     *
     * @return list<array{kind: string, id: int, title: string, detail: string, badge: string, at: string|null}>
     */
    public function needsAttention(Workspace $workspace): array
    {
        $tickets = $workspace->tickets()
            ->where('status', TicketStatus::Open)
            ->where(fn ($query) => $query
                ->whereIn('priority', [TicketPriority::Urgent, TicketPriority::High])
                ->orWhereHas('aiSuggestions', fn ($suggestions) => $suggestions->pending()->where('field', 'priority')->whereIn('suggested_value->value', ['urgent', 'high'])))
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 else 3 end")
            ->orderBy('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Ticket $ticket): array => [
                'kind' => 'ticket',
                'id' => $ticket->id,
                'title' => "{$ticket->reference()} {$ticket->subject}",
                'detail' => $ticket->summary ?? $ticket->requester_name,
                'badge' => $ticket->priority?->label() ?? 'AI: high priority',
                'at' => $ticket->created_at?->toIso8601String(),
            ]);

        $leads = $workspace->leads()
            ->where('status', LeadStatus::New)
            ->where(fn ($query) => $query
                ->where('score', LeadScore::Hot)
                ->orWhereHas('aiSuggestions', fn ($suggestions) => $suggestions->pending()->where('field', 'lead_score')->where('suggested_value->value', 'hot')))
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (Lead $lead): array => [
                'kind' => 'lead',
                'id' => $lead->id,
                'title' => $lead->company ? "{$lead->name} · {$lead->company}" : $lead->name,
                'detail' => $lead->score_reason ?? 'Scored hot by AI, waiting for review.',
                'badge' => 'Hot lead',
                'at' => $lead->created_at?->toIso8601String(),
            ]);

        return array_values($tickets->concat($leads)->all());
    }

    /**
     * @return array{date: string, headline: string|null, summary: string|null}|null
     */
    public function latestBrief(Workspace $workspace): ?array
    {
        $brief = $workspace->dailyBriefs()->where('generation_status', AiTaskStatus::Completed)->orderByDesc('brief_date')->first();

        return $brief === null ? null : [
            'date' => $brief->brief_date->toDateString(),
            'headline' => $brief->content['headline'] ?? null,
            'summary' => $brief->content['summary'] ?? null,
        ];
    }

    public function aiSpendToday(Workspace $workspace): float
    {
        return round((float) AiUsageLog::query()->whereBelongsTo($workspace)->today()->sum('estimated_cost_usd'), 4);
    }
}
