<?php

namespace App\Jobs;

use App\Actions\Tickets\GenerateReplyDraft;

class DraftTicketReply extends TicketAiJob
{
    protected function task(): string
    {
        return 'draft';
    }

    protected function run(): void
    {
        app(GenerateReplyDraft::class)->handle($this->ticket);
    }
}
