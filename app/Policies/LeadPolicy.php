<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeadPolicy
{
    public function view(User $user, Lead $lead): Response
    {
        return $user->workspace_id === $lead->workspace_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Lead $lead): Response
    {
        return $this->view($user, $lead);
    }
}
