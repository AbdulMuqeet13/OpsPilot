<?php

namespace App\Policies;

use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketCategoryPolicy
{
    public function manage(User $user, TicketCategory $category): Response
    {
        if ($user->workspace_id !== $category->workspace_id) {
            return Response::denyAsNotFound();
        }

        return $user->isAdmin() ? Response::allow() : Response::deny();
    }
}
