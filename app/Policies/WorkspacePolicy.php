<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can change workspace-wide settings such as branding.
     */
    public function manageSettings(User $user, Workspace $workspace): bool
    {
        return $user->workspace_id === $workspace->id && $user->isAdmin();
    }
}
