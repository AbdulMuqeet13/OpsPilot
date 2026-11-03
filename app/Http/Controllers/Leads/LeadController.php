<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadDetailResource;
use App\Http\Resources\LeadListItemResource;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;
        $filters = [
            'score' => $request->string('score')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->trim()->limit(100, '')->toString() ?: null,
        ];

        $leads = $workspace->leads()
            ->with(['aiSuggestions' => fn (MorphMany $relation) => $relation->pending()])
            ->when($filters['score'] === 'unscored', fn (Builder $query) => $query->whereNull('score'))
            ->when(LeadScore::tryFrom((string) $filters['score']), fn (Builder $query, LeadScore $score) => $query->where('score', $score))
            ->when(LeadStatus::tryFrom((string) $filters['status']), fn (Builder $query, LeadStatus $status) => $query->where('status', $status))
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('leads/index', [
            'leads' => $leads->through(fn (Lead $lead): array => (new LeadListItemResource($lead))->resolve($request)),
            'filters' => $filters,
            'scoreCounts' => $workspace->leads()
                ->toBase()
                ->selectRaw("coalesce(score, 'unscored') as bucket, count(*) as total")
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn (mixed $total): int => (int) $total),
            'statuses' => array_map(fn (LeadStatus $status): array => ['value' => $status->value, 'label' => $status->label()], LeadStatus::cases()),
            'leadFormUrl' => route('public.leads.create', $workspace),
            'hasProfile' => filled($workspace->ideal_customer_profile),
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->load(['aiSuggestions.decider:id,name', 'followUpSender:id,name']);

        return Inertia::render('leads/show', [
            'lead' => (new LeadDetailResource($lead))->resolve($request),
            'statuses' => array_map(fn (LeadStatus $status): array => ['value' => $status->value, 'label' => $status->label()], LeadStatus::cases()),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->status = $request->enum('status', LeadStatus::class) ?? $lead->status;
        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead updated.']);

        return back();
    }
}
