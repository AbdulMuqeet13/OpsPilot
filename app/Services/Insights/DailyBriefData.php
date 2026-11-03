<?php

namespace App\Services\Insights;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Gathers one day of activity for the Daily Brief: exact numbers are computed
 * here (never by the AI), and a compact digest is prepared for the AI to read.
 */
class DailyBriefData
{
    private const int MAX_TICKETS = 50;

    private const int MAX_LEADS = 30;

    /**
     * @return array{stats: array{tickets: int, leads: int, hot_leads: int, urgent_open: int, average_sentiment: float|null, by_category: array<array-key, int>}, digest: array<string, mixed>, urgent_open_tickets: list<array{number: int, subject: string, priority: string}>, hot_leads_waiting: list<array{id: int, name: string, company: string|null}>}
     */
    public function collect(Workspace $workspace, CarbonImmutable $date): array
    {
        $tickets = $workspace->tickets()
            ->with('category:id,name')
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->orderBy('number')
            ->get();

        $leads = $workspace->leads()
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->orderBy('id')
            ->get();

        $urgentOpen = $workspace->tickets()
            ->where('status', TicketStatus::Open)
            ->whereIn('priority', [TicketPriority::Urgent, TicketPriority::High])
            ->orderByRaw("case priority when 'urgent' then 1 else 2 end")
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'number', 'subject', 'priority']);

        $hotWaiting = $workspace->leads()
            ->where('score', LeadScore::Hot)
            ->where('status', LeadStatus::New)
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'name', 'company']);

        $sentiments = $tickets->whereNotNull('sentiment_score')->map(fn (Ticket $ticket): float => (float) $ticket->sentiment_score);

        $stats = [
            'tickets' => $tickets->count(),
            'leads' => $leads->count(),
            'hot_leads' => $leads->where('score', LeadScore::Hot)->count(),
            'urgent_open' => $workspace->tickets()->where('status', TicketStatus::Open)->where('priority', TicketPriority::Urgent)->count(),
            'average_sentiment' => $sentiments->isEmpty() ? null : round($sentiments->avg(), 2),
            'by_category' => $tickets->groupBy(fn (Ticket $ticket): string => $ticket->category->name ?? 'Uncategorised')->map->count()->sortDesc()->all(),
        ];

        return [
            'stats' => $stats,
            'digest' => [
                'date' => $date->toFormattedDayDateString(),
                'totals' => $stats,
                'new_tickets' => $tickets->take(self::MAX_TICKETS)->map(fn (Ticket $ticket): array => [
                    'number' => $ticket->number,
                    'subject' => $ticket->subject,
                    'summary' => $ticket->summary ?? Str::limit($ticket->body, 200),
                    'category' => $ticket->category?->name,
                    'priority' => $ticket->priority?->value,
                    'sentiment' => $ticket->sentiment?->value,
                    'status' => $ticket->status->value,
                ])->values()->all(),
                'new_leads' => $leads->take(self::MAX_LEADS)->map(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'company' => $lead->company,
                    'score' => $lead->score?->value,
                    'score_reason' => $lead->score_reason ? Str::limit($lead->score_reason, 160) : null,
                ])->values()->all(),
                'urgent_tickets_still_open' => $urgentOpen->map(fn (Ticket $ticket): array => [
                    'number' => $ticket->number,
                    'subject' => $ticket->subject,
                    'priority' => $ticket->priority?->value,
                ])->values()->all(),
                'hot_leads_not_contacted' => $hotWaiting->map(fn (Lead $lead): array => ['id' => $lead->id, 'name' => $lead->name, 'company' => $lead->company])->values()->all(),
            ],
            'urgent_open_tickets' => array_values($urgentOpen->map(fn (Ticket $ticket): array => [
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'priority' => (string) $ticket->priority?->value,
            ])->all()),
            'hot_leads_waiting' => array_values($hotWaiting->map(fn (Lead $lead): array => ['id' => $lead->id, 'name' => $lead->name, 'company' => $lead->company])->all()),
        ];
    }
}
