<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\RunLeadImportRequest;
use App\Http\Requests\Leads\StoreLeadImportRequest;
use App\Jobs\ImportLeads;
use App\Models\LeadImport;
use App\Services\Leads\CsvLeadReader;
use App\Services\Leads\LeadImportLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadImportController extends Controller
{
    public function create(Request $request, LeadImportLimits $limits): Response
    {
        return Inertia::render('leads/import', [
            'recentImports' => $request->user()->workspace->leadImports()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (LeadImport $import): array => [
                    'id' => $import->id,
                    'filename' => $import->original_filename,
                    'status' => ['value' => $import->status->value, 'label' => $import->status->label()],
                    'importedRows' => $import->imported_rows,
                    'createdAt' => $import->created_at?->toIso8601String(),
                ]),
            'limits' => [
                'maxKilobytes' => $limits->maxKilobytes(),
                'maxRows' => $limits->maxRows(),
                'maxFilesPerUser' => $limits->maxFilesPerUser(),
            ],
        ]);
    }

    /**
     * Step 1: upload and inspect the file, then ask the user to confirm the column mapping.
     */
    public function store(StoreLeadImportRequest $request, CsvLeadReader $reader): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('lead-imports', 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be saved. Please try again.']);
        }

        $summary = $reader->inspect(Storage::disk('local')->path($path));

        if ($summary['headers'] === [] || $summary['total'] === 0) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages(['file' => 'The file needs a header row and at least one lead.']);
        }

        $import = new LeadImport;
        $import->workspace_id = $request->user()->workspace_id;
        $import->user_id = $request->user()->id;
        $import->original_filename = mb_substr($file->getClientOriginalName(), 0, 255);
        $import->path = $path;
        $import->status = LeadImportStatus::Uploaded;
        $import->headers = $summary['headers'];
        $import->preview_rows = $summary['preview'];
        $import->column_map = $reader->guessColumnMap($summary['headers']);
        $import->total_rows = $summary['total'];
        $import->save();

        return redirect()->route('lead-imports.show', $import);
    }

    public function show(LeadImport $import, LeadImportLimits $limits): Response
    {
        Gate::authorize('view', $import);

        return Inertia::render('leads/import-show', [
            'import' => [
                'id' => $import->id,
                'filename' => $import->original_filename,
                'status' => ['value' => $import->status->value, 'label' => $import->status->label()],
                'headers' => $import->headers,
                'previewRows' => $import->preview_rows,
                'columnMap' => $import->column_map,
                'totalRows' => $import->total_rows,
                'importedRows' => $import->imported_rows,
                'skippedRows' => $import->skipped_rows,
                'rowErrors' => $import->row_errors ?? [],
                'failureMessage' => $import->failure_message,
            ],
            'fields' => array_keys(CsvLeadReader::FIELDS),
            'maxRows' => $limits->maxRows(),
        ]);
    }

    /**
     * Step 2: confirm the mapping and queue the import.
     */
    public function run(RunLeadImportRequest $request, LeadImport $import): RedirectResponse
    {
        $import->column_map = $request->columnMap();
        $import->score_leads = $request->boolean('score_leads', true);
        $import->status = LeadImportStatus::Queued;
        $import->save();

        ImportLeads::dispatch($import);

        return back();
    }
}
