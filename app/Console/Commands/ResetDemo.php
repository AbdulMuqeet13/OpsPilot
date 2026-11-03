<?php

namespace App\Console\Commands;

use App\Actions\Demo\ResetShowcaseWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset
    {--sandboxes-only : Only remove expired visitor sandboxes; leave the showcase workspace alone}
    {--all-sandboxes : Remove every visitor sandbox, not just expired ones}
    {--force : Run even when DEMO_MODE is off}')]
#[Description('Remove visitor sandboxes and restore the Oakline Home showcase workspace to its sample data')]
class ResetDemo extends Command
{
    public function handle(DeleteWorkspace $deleteWorkspace, ResetShowcaseWorkspace $resetShowcase): int
    {
        if (! config('demo.enabled') && ! $this->option('force')) {
            $this->error('DEMO_MODE is off, so nothing was reset. This command deletes data; pass --force if you really mean it.');

            return self::FAILURE;
        }

        $sandboxes = Workspace::query()->where('is_sandbox', true);

        if (! $this->option('all-sandboxes')) {
            $sandboxes->expiredSandboxes();
        }

        $removed = 0;

        $sandboxes->lazyById()->each(function (Workspace $workspace) use ($deleteWorkspace, &$removed): void {
            $deleteWorkspace->handle($workspace);
            $removed++;
        });

        $this->info("Removed {$removed} sandbox workspaces.");

        if ($this->option('sandboxes-only')) {
            return self::SUCCESS;
        }

        $showcase = $resetShowcase->handle();

        $this->info("Restored the showcase workspace: {$showcase->tickets()->count()} tickets and {$showcase->leads()->count()} leads.");
        $this->line('Sign in as '.config('demo.showcase.admin_email').' (admin) or '.config('demo.showcase.agent_email').' (agent).');

        return self::SUCCESS;
    }
}
