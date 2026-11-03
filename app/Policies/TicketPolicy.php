<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Admins and agents can work any ticket in their workspace. Other workspaces' tickets "don't exist".
     */
    public function view(User $user, Ticket $ticket): Response
    {
        return $user->workspace_id === $ticket->workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Ticket $ticket): Response
    {
        return $this->view($user, $ticket);
    }
}
