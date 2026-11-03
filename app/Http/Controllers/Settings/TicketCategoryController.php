<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CategoryColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TicketCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/ticket-categories', [
            'categories' => $workspace->ticketCategories()
                ->withCount('tickets')
                ->get()
                ->map(fn (TicketCategory $category): array => [
                    ...$category->toBadgeArray(),
                    'description' => $category->description,
                    'ticketsCount' => (int) $category->getAttribute('tickets_count'),
                ]),
            'colors' => array_map(fn (CategoryColor $color): string => $color->value, CategoryColor::cases()),
            'maxCategories' => TicketCategoryRequest::MAX_CATEGORIES,
        ]);
    }

    public function store(TicketCategoryRequest $request): RedirectResponse
    {
        $workspace = $request->user()->workspace;

        $workspace->ticketCategories()->create([
            ...$request->safe()->only(['name', 'description', 'color']),
            'position' => (int) $workspace->ticketCategories()->max('position') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category added. The AI will start using it on new tickets.']);

        return back();
    }

    public function update(TicketCategoryRequest $request, TicketCategory $category): RedirectResponse
    {
        $category->update($request->safe()->only(['name', 'description', 'color']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated.']);

        return back();
    }

    public function destroy(Request $request, TicketCategory $category): RedirectResponse
    {
        Gate::authorize('manage', $category);

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category “{$category->name}” removed. Its tickets are now uncategorised."]);

        return back();
    }
}
