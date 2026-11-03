<?php

namespace App\Actions\Demo;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDemoSandbox
{
    public const string COMPANY_NAME = 'Oakline Home';

    public function __construct(
        private CreateWorkspace $createWorkspace,
        private SeedOaklineSampleData $seedSampleData,
    ) {}

    /**
     * Spin up a private, expiring copy of the demo company and return the user to sign in as.
     */
    public function handle(UserRole $role): User
    {
        return DB::transaction(function () use ($role): User {
            $workspace = $this->createWorkspace->handle(self::COMPANY_NAME, [
                'is_sandbox' => true,
                'expires_at' => now()->addHours(config('demo.sandbox.lifetime_hours')),
                'primary_color' => OaklineHomeSampleData::BRAND_COLOR,
                'support_email' => OaklineHomeSampleData::SUPPORT_EMAIL,
            ]);

            $members = [
                UserRole::Admin->value => $this->createMember($workspace->id, 'Maya Brooks', UserRole::Admin),
                UserRole::Agent->value => $this->createMember($workspace->id, 'Theo Ramirez', UserRole::Agent),
            ];

            $this->seedSampleData->handle($workspace);

            return $members[$role->value];
        });
    }

    private function createMember(int $workspaceId, string $name, UserRole $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => Str::slug($name, '.').'.'.Str::lower(Str::random(8)).'@demo.oaklinehome.example',
            'password' => Str::password(32),
        ]);
        $user->workspace_id = $workspaceId;
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
