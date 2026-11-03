<?php

namespace App\Policies;

use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeadImportPolicy
{
    public function view(User $user, LeadImport $import): Response
    {
        return $user->workspace_id === $import->workspace_id ? Response::allow() : Response::denyAsNotFound();
    }
}
