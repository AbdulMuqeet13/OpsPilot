<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function store(StoreApiTicketRequest $request, CreateTicket $createTicket): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $ticket = $createTicket->handle($workspace, TicketSource::Api, $request->ticketInput());

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }
}
