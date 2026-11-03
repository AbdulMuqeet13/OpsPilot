<?php

namespace App\Jobs;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadImportStatus;
use App\Enums\LeadSource;
use App\Models\LeadImport;
use App\Services\Leads\CsvLeadReader;
use App\Services\Leads\LeadImportLimits;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ImportLeads implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Demo resets delete whole workspaces; jobs for records that no longer exist are dropped quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * Only the first errors are kept, so a broken file can't bloat the database.
     */
    private const int MAX_STORED_ERRORS = 25;

    public function __construct(public LeadImport $import)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->import->id;
    }

    public function handle(CsvLeadReader $reader, CreateLead $createLead, LeadImportLimits $limits): void
    {
        $this->import->forceFill(['status' => LeadImportStatus::Processing])->save();

        $workspace = $this->import->workspace;
        $map = $this->import->column_map ?? [];
        $existingEmails = $workspace->leads()->pluck('email')->map(fn (string $email): string => Str::lower($email))->flip()->all();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($reader->dataRows(Storage::disk('local')->path($this->import->path)) as $rowNumber => $cells) {
            if ($imported + $skipped >= $limits->maxRows()) {
                $errors[] = ['row' => $rowNumber, 'message' => "Stopped after {$limits->maxRows()} rows, the import limit."];
                break;
            }

            $input = $this->leadInput($cells, $map);
            $validator = Validator::make($input, [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255'],
            ]);

            $problem = match (true) {
                $validator->fails() => $validator->errors()->first(),
                isset($existingEmails[Str::lower($input['email'])]) => 'A lead with this email already exists.',
                default => null,
            };

            if ($problem !== null) {
                $skipped++;

                if (count($errors) < self::MAX_STORED_ERRORS) {
                    $errors[] = ['row' => $rowNumber, 'message' => $problem];
                }

                continue;
            }

            $createLead->handle($workspace, LeadSource::Csv, $input, $this->import, runAi: $this->import->score_leads);
            $existingEmails[Str::lower($input['email'])] = true;
            $imported++;
        }

        $this->import->forceFill([
            'status' => LeadImportStatus::Completed,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'row_errors' => $errors,
            'completed_at' => now(),
        ])->save();

        Storage::disk('local')->delete($this->import->path);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->forceFill([
            'status' => LeadImportStatus::Failed,
            'failure_message' => 'The file could not be imported. Check that it is a valid CSV and try again.',
        ])->save();
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int|null>  $map
     * @return array{name: string, email: string, company: string|null, job_title: string|null, company_size: string|null, budget: string|null, website: string|null, message: string|null}
     */
    private function leadInput(array $cells, array $map): array
    {
        $cell = fn (string $field, int $limit = 255): ?string => isset($map[$field], $cells[$map[$field]]) && $cells[$map[$field]] !== ''
            ? Str::limit($cells[$map[$field]], $limit, '')
            : null;

        $name = $cell('name', 120) ?? trim(($cell('first_name') ?? '').' '.($cell('last_name') ?? ''));

        return [
            'name' => Str::limit($name, 120, ''),
            'email' => Str::lower($cell('email') ?? ''),
            'company' => $cell('company', 120),
            'job_title' => $cell('job_title', 120),
            'company_size' => $cell('company_size', 40),
            'budget' => $cell('budget', 40),
            'website' => $cell('website'),
            'message' => $cell('message', 2000),
        ];
    }
}
