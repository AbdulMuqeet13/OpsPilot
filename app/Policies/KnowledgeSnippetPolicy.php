<?php

namespace App\Policies;

use App\Models\KnowledgeSnippet;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class KnowledgeSnippetPolicy
{
    public function manage(User $user, KnowledgeSnippet $snippet): Response
    {
        if ($user->workspace_id !== $snippet->workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin() ? Response::allow() : Response::deny();
    }
}
