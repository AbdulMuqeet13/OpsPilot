<?php

namespace App\Policies;

use App\Models\AiSuggestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AiSuggestionPolicy
{
    public function decide(User $user, AiSuggestion $suggestion): Response
    {
        return $user->workspace_id === $suggestion->workspace_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
