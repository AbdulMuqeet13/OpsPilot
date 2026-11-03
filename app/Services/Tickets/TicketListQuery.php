<?php

namespace App\Services\Tickets;

use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Pagination\LengthAwarePaginator;

class TicketListQuery
{
    /**
     * @param  array{status?: string|null, priority?: string|null, category?: int|null, search?: string|null, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginate(Workspace $workspace, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->whereBelongsTo($workspace)
            ->with([
                'category',
                'assignee:id,name',
                'aiSuggestions' => fn (MorphMany $relation) => $relation->pending()->whereIn('field', SuggestionField::ticketTriageFields()),
            ]);

        $status = TicketStatus::tryFrom((string) ($filters['status'] ?? TicketStatus::Open->value));
        if ($status !== null) {
            $query->where('status', $status);
        }

        $priority = TicketPriority::tryFrom((string) ($filters['priority'] ?? ''));
        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $number = ltrim($search, '#');

                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");

                if (ctype_digit($number)) {
                    $query->orWhere('number', (int) $number);
                }
            });
        }

        if (($filters['sort'] ?? null) === 'priority') {
            // Static CASE, no user input: urgent first, unprioritised last.
            $query->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 when 'low' then 4 else 5 end");
        }

        return $query
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
