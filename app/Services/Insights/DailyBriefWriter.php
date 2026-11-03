<?php

namespace App\Services\Insights;

use App\Enums\AiFeature;
use App\Models\DailyBrief;
use App\Models\Lead;
use App\Models\Ticket;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Prompts\DailyBriefPrompt;
use Illuminate\Support\Str;

/**
 * Fills a DailyBrief. Quiet days are written locally without an AI call;
 * otherwise the AI writes the narrative and every reference it returns is
 * checked against real tickets and leads before it is stored.
 */
class DailyBriefWriter
{
    public function __construct(
        private DailyBriefData $data,
        private AiGateway $gateway,
    ) {}

    public function write(DailyBrief $brief): void
    {
        $workspace = $brief->workspace;
        $collected = $this->data->collect($workspace, $brief->brief_date->toImmutable());
        $stats = $collected['stats'];

        if ($stats['tickets'] === 0 && $stats['leads'] === 0) {
            $brief->forceFill([
                'stats' => $stats,
                'content' => $this->quietDayContent($brief, $collected),
                'ai_usage_log_id' => null,
                'generated_at' => now(),
            ])->save();

            return;
        }

        $prompt = new DailyBriefPrompt($workspace, $collected['digest']);
        $result = $this->gateway->structured(
            new AiCallContext(AiFeature::DailyBrief, $workspace, $brief->requester, $brief),
            $prompt->request(),
            $prompt->schema(),
        );

        $brief->forceFill([
            'stats' => $stats,
            'content' => $this->sanitise($brief, $result->data),
            'ai_usage_log_id' => $result->usageLogId,
            'generated_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{headline: string, summary: string, themes: list<array{title: string, detail: string, tickets: list<array{id: int, number: int}>}>, urgent_items: list<array{kind: string, id: int, label: string, reason: string}>}
     */
    private function sanitise(DailyBrief $brief, array $data): array
    {
        $workspaceId = $brief->workspace_id;
        $rawThemes = $this->listOfObjects($data['themes'] ?? []);
        $rawUrgentItems = $this->listOfObjects($data['urgent_items'] ?? []);
        $ticketNumbers = collect($rawThemes)->flatMap(fn (array $theme): array => (array) ($theme['ticket_numbers'] ?? []))
            ->merge(collect($rawUrgentItems)->where('kind', 'ticket')->pluck('id'))
            ->map(fn (mixed $number): int => (int) $number)
            ->unique();
        $tickets = Ticket::query()->where('workspace_id', $workspaceId)->whereIn('number', $ticketNumbers)->get(['id', 'number', 'subject'])->keyBy('number');
        $leadIds = collect($rawUrgentItems)->where('kind', 'lead')->pluck('id')->map(fn (mixed $id): int => (int) $id);
        $leads = Lead::query()->where('workspace_id', $workspaceId)->whereIn('id', $leadIds)->get(['id', 'name', 'company'])->keyBy('id');

        $themes = collect($rawThemes)->take(4)->map(fn (array $theme): array => [
            'title' => Str::limit(trim((string) ($theme['title'] ?? '')), 80, '…'),
            'detail' => Str::limit(trim((string) ($theme['detail'] ?? '')), 300, '…'),
            // toBase(): Eloquent's only() filters by primary key, but $tickets is keyed by ticket number.
            'tickets' => array_values($tickets
                ->toBase()
                ->only(array_map(intval(...), (array) ($theme['ticket_numbers'] ?? [])))
                ->map(fn (Ticket $ticket): array => ['id' => $ticket->id, 'number' => $ticket->number])
                ->all()),
        ])->filter(fn (array $theme): bool => $theme['title'] !== '')->all();

        $urgentItems = collect($rawUrgentItems)->map(function (array $item) use ($tickets, $leads): ?array {
            $reason = Str::limit(trim((string) ($item['reason'] ?? '')), 200, '…');

            if (($item['kind'] ?? null) === 'ticket' && ($ticket = $tickets->get((int) $item['id'])) instanceof Ticket) {
                return ['kind' => 'ticket', 'id' => $ticket->id, 'label' => "#{$ticket->number} {$ticket->subject}", 'reason' => $reason];
            }

            if (($item['kind'] ?? null) === 'lead' && ($lead = $leads->get((int) $item['id'])) instanceof Lead) {
                return ['kind' => 'lead', 'id' => $lead->id, 'label' => $lead->company ? "{$lead->name} ({$lead->company})" : $lead->name, 'reason' => $reason];
            }

            return null;
        })->filter()->take(5)->all();

        return [
            'headline' => Str::limit(trim((string) ($data['headline'] ?? '')), 120, '…'),
            'summary' => Str::limit(trim((string) ($data['summary'] ?? '')), 1000, '…'),
            'themes' => array_values($themes),
            'urgent_items' => array_values($urgentItems),
        ];
    }

    /**
     * The AI's arrays, keeping only entries that are JSON objects.
     *
     * @return list<array<array-key, mixed>>
     */
    private function listOfObjects(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * @param  array{urgent_open_tickets: list<array{number: int, subject: string, priority: string}>, hot_leads_waiting: list<array{id: int, name: string, company: string|null}>}  $collected
     * @return array{headline: string, summary: string, themes: list<array{title: string, detail: string, tickets: list<array{id: int, number: int}>}>, urgent_items: list<array{kind: string, id: int, label: string, reason: string}>}
     */
    private function quietDayContent(DailyBrief $brief, array $collected): array
    {
        $tickets = Ticket::query()->where('workspace_id', $brief->workspace_id)
            ->whereIn('number', array_column($collected['urgent_open_tickets'], 'number'))
            ->get(['id', 'number', 'subject']);

        $items = $tickets->map(fn (Ticket $ticket): array => ['kind' => 'ticket', 'id' => $ticket->id, 'label' => "#{$ticket->number} {$ticket->subject}", 'reason' => 'Still open and marked high priority or urgent.'])
            ->concat(array_map(fn (array $lead): array => [
                'kind' => 'lead',
                'id' => $lead['id'],
                'label' => $lead['company'] ? "{$lead['name']} ({$lead['company']})" : $lead['name'],
                'reason' => 'Hot lead that hasn\'t been contacted yet.',
            ], $collected['hot_leads_waiting']))
            ->take(5)
            ->all();

        return [
            'headline' => 'A quiet day',
            'summary' => "No new tickets or leads arrived on {$brief->brief_date->toFormattedDayDateString()}.".($items === [] ? ' Nothing is waiting on the team.' : ' A few earlier items still need attention.'),
            'themes' => [],
            'urgent_items' => array_values($items),
        ];
    }
}
