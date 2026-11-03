<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

abstract class TicketAiJob extends AiTaskJob
{
    public function __construct(public Ticket $ticket)
    {
        $this->afterCommit();
    }

    protected function trackedModel(): Model
    {
        return $this->ticket;
    }
}
