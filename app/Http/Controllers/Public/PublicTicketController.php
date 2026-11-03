<?php

namespace App\Http\Controllers\Public;

use App\Actions\Tickets\CreateTicket;
use App\Data\Brand;
use App\Enums\TicketSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePublicTicketRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicTicketController extends Controller
{
    public function create(Workspace $workspace): Response
    {
        return Inertia::render('public/contact', [
            'brand' => Brand::fromWorkspace($workspace)->toArray(),
            'workspaceSlug' => $workspace->slug,
        ]);
    }

    public function store(StorePublicTicketRequest $request, Workspace $workspace, CreateTicket $createTicket): RedirectResponse
    {
        // Pretend success to bots so they don't adapt; nothing is stored.
        $reference = $request->isLikelySpam()
            ? '#'.random_int(1000, 9999)
            : $createTicket->handle($workspace, TicketSource::Form, $request->ticketInput())->reference();

        return Inertia::flash('submittedReference', $reference)->back();
    }
}
