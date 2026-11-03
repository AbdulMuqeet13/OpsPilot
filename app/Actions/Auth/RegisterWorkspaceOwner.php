<?php

namespace App\Actions\Auth;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterWorkspaceOwner
{
    public function __construct(private CreateWorkspace $createWorkspace) {}

    /**
     * Create a new workspace and its first admin.
     *
     * @param  array{name: string, email: string, password: string, company_name: string}  $input
     */
    public function handle(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            $workspace = $this->createWorkspace->handle($input['company_name'], [
                'support_email' => $input['email'],
            ]);

            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->role = UserRole::Admin;
            $user->workspace()->associate($workspace);
            $user->save();

            return $user;
        });
    }
}
