<?php

namespace App\Actions\Demo;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The permanent Oakline Home workspace you sign in to for your own presentations
 * (sandboxes are for visitors). Resetting wipes everything people created in it
 * and restores the original sample data, branding and sign-ins.
 */
class ResetShowcaseWorkspace
{
    public function __construct(
        private CreateWorkspace $createWorkspace,
        private DeleteWorkspace $deleteWorkspace,
        private SeedOaklineSampleData $seedSampleData,
    ) {}

    public function handle(): Workspace
    {
        return DB::transaction(function (): Workspace {
            $slug = (string) config('demo.showcase.slug');
            $workspace = Workspace::query()->where('slug', $slug)->first();

            if ($workspace === null) {
                $workspace = $this->createWorkspace->handle(CreateDemoSandbox::COMPANY_NAME);
                $workspace->forceFill(['slug' => $slug])->save();
            } else {
                $this->wipe($workspace);
            }

            $workspace->forceFill([
                'name' => CreateDemoSandbox::COMPANY_NAME,
                'logo_path' => null,
                'api_key_hash' => null,
                'api_key_last_four' => null,
                'api_key_created_at' => null,
                'is_sandbox' => false,
                'expires_at' => null,
            ])->save();

            $this->ensureUser($workspace, 'Maya Brooks', (string) config('demo.showcase.admin_email'), UserRole::Admin);
            $this->ensureUser($workspace, 'Theo Ramirez', (string) config('demo.showcase.agent_email'), UserRole::Agent);

            $this->seedSampleData->handle($workspace);

            return $workspace;
        });
    }

    private function wipe(Workspace $workspace): void
    {
        $this->deleteWorkspace->deleteFiles($workspace);

        $workspace->aiSuggestions()->delete();
        $workspace->dailyBriefs()->delete();
        $workspace->aiUsageLogs()->delete();
        $workspace->leads()->delete();
        $workspace->leadImports()->delete();
        $workspace->tickets()->delete();
        $workspace->ticketCategories()->delete();
        $workspace->knowledgeSnippets()->delete();
        $workspace->aiFeatureSettings()->delete();
        $workspace->users()->whereNotIn('email', [config('demo.showcase.admin_email'), config('demo.showcase.agent_email')])->delete();
    }

    private function ensureUser(Workspace $workspace, string $name, string $email, UserRole $role): void
    {
        $user = User::query()->where('email', $email)->first() ?? new User;
        $user->forceFill([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'email' => $email,
            'password' => (string) config('demo.showcase.password'),
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();
    }
}
