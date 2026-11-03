<?php

namespace App\Actions\Workspaces;

use App\Models\LeadImport;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

class DeleteWorkspace
{
    /**
     * Delete a workspace, its uploaded files, and (through foreign keys) all of its records.
     */
    public function handle(Workspace $workspace): void
    {
        $this->deleteFiles($workspace);

        $workspace->delete();
    }

    public function deleteFiles(Workspace $workspace): void
    {
        Storage::disk(config('opspilot.logo.disk'))->deleteDirectory("workspaces/{$workspace->id}");

        $workspace->leadImports()->each(fn (LeadImport $import) => Storage::disk('local')->delete($import->path));
    }
}
