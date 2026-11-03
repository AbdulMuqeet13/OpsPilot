<?php

use App\Enums\LeadImportStatus;
use App\Enums\LeadSource;
use App\Jobs\DraftLeadFollowUp;
use App\Jobs\ImportLeads;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function fixtureCsv(string $name): UploadedFile
{
    return new UploadedFile(base_path("tests/Fixtures/leads/{$name}"), $name, 'text/csv', null, true);
}

describe('upload', function () {
    it('inspects the file and guesses the column mapping', function () {
        Storage::fake('local');
        $user = User::factory()->agent()->create();

        $response = actingAs($user)->post(route('lead-imports.store'), ['file' => fixtureCsv('leads.csv')]);

        $import = LeadImport::sole();
        $response->assertRedirect(route('lead-imports.show', $import));

        expect($import)
            ->status->toBe(LeadImportStatus::Uploaded)
            ->user_id->toBe($user->id)
            ->total_rows->toBe(4)
            ->headers->toBe(['Full name', 'Email', 'Company', 'Job title', 'Company size', 'Budget', 'Notes'])
            ->and($import->column_map)->toMatchArray([
                'name' => 0,
                'email' => 1,
                'company' => 2,
                'job_title' => 3,
                'company_size' => 4,
                'budget' => 5,
                'message' => 6,
                'website' => null,
            ])
            ->and($import->preview_rows[0][0])->toBe('Dana Whitfield');

        Storage::disk('local')->assertExists($import->path);
    });

    it('handles semicolon delimiters and a byte order mark', function () {
        Storage::fake('local');

        actingAs(User::factory()->create())->post(route('lead-imports.store'), ['file' => fixtureCsv('semicolon-bom.csv')]);

        expect(LeadImport::sole())
            ->headers->toBe(['First name', 'Last name', 'E-mail', 'Organisation'])
            ->column_map->toMatchArray(['first_name' => 0, 'last_name' => 1, 'email' => 2, 'company' => 3, 'name' => null]);
    });

    it('rejects files that are not CSV', function () {
        Storage::fake('local');

        actingAs(User::factory()->create())
            ->post(route('lead-imports.store'), ['file' => UploadedFile::fake()->create('leads.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
            ->assertSessionHasErrors(['file' => 'Upload a .csv file.']);

        expect(LeadImport::count())->toBe(0);
    });

    it('rejects files over the demo size limit', function () {
        config(['demo.enabled' => true, 'demo.uploads.max_kilobytes' => 1]);
        Storage::fake('local');

        actingAs(User::factory()->create())
            ->post(route('lead-imports.store'), ['file' => UploadedFile::fake()->createWithContent('leads.csv', "Name,Email\n".str_repeat("Dana,dana@example.com\n", 200))])
            ->assertSessionHasErrors(['file' => 'The file is too large. The limit is 1 KB.']);
    });

    it('limits how many files one person can import in the demo', function () {
        config(['demo.enabled' => true, 'demo.uploads.max_files_per_user' => 1]);
        Storage::fake('local');
        $user = User::factory()->create();
        LeadImport::factory()->for($user->workspace)->create(['user_id' => $user->id]);

        actingAs($user)
            ->post(route('lead-imports.store'), ['file' => fixtureCsv('leads.csv')])
            ->assertSessionHasErrors('file');
    });

    it('rejects a file with no data rows', function () {
        Storage::fake('local');

        actingAs(User::factory()->create())
            ->post(route('lead-imports.store'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', "Name,Email\n")])
            ->assertSessionHasErrors(['file' => 'The file needs a header row and at least one lead.']);
    });
});

describe('run', function () {
    it('saves the confirmed mapping and queues the import', function () {
        Queue::fake([ImportLeads::class]);
        $user = User::factory()->create();
        $import = LeadImport::factory()->for($user->workspace)->create();

        actingAs($user)
            ->post(route('lead-imports.run', $import), ['column_map' => ['name' => 0, 'email' => 1, 'company' => 2], 'score_leads' => false])
            ->assertRedirect();

        expect($import->fresh())
            ->status->toBe(LeadImportStatus::Queued)
            ->score_leads->toBeFalse()
            ->column_map->toMatchArray(['name' => 0, 'email' => 1, 'company' => 2, 'message' => null]);

        Queue::assertPushed(ImportLeads::class);
    });

    it('requires an email column and a name column', function () {
        $user = User::factory()->create();
        $import = LeadImport::factory()->for($user->workspace)->create();

        actingAs($user)
            ->post(route('lead-imports.run', $import), ['column_map' => ['company' => 2]])
            ->assertSessionHasErrors([
                'column_map.email' => 'Choose the column with the email address.',
                'column_map.name' => 'Choose the column with the lead\'s name (or first name).',
            ]);
    });

    it('rejects column numbers that are not in the file', function () {
        $user = User::factory()->create();
        $import = LeadImport::factory()->for($user->workspace)->create();

        actingAs($user)
            ->post(route('lead-imports.run', $import), ['column_map' => ['name' => 0, 'email' => 9]])
            ->assertSessionHasErrors('column_map.email');
    });

    it('refuses to run the same import twice', function () {
        $user = User::factory()->create();
        $import = LeadImport::factory()->for($user->workspace)->create(['status' => LeadImportStatus::Completed]);

        actingAs($user)
            ->post(route('lead-imports.run', $import), ['column_map' => ['name' => 0, 'email' => 1]])
            ->assertSessionHasErrors(['column_map' => 'This file has already been imported.']);
    });

    it('returns 404 for another workspace\'s import', function () {
        $import = LeadImport::factory()->for(Workspace::factory())->create();

        actingAs(User::factory()->create())->get(route('lead-imports.show', $import))->assertNotFound();
        actingAs(User::factory()->create())->post(route('lead-imports.run', $import), ['column_map' => ['name' => 0, 'email' => 1]])->assertNotFound();
    });

    it('shows import progress', function () {
        $user = User::factory()->create();
        $import = LeadImport::factory()->for($user->workspace)->create(['status' => LeadImportStatus::Processing]);

        actingAs($user)
            ->get(route('lead-imports.show', $import))
            ->assertInertia(fn (Assert $page) => $page->component('leads/import-show')->where('import.status.value', 'processing'));
    });
});

describe('the import job', function () {
    function importFromFixture(string $fixture, array $columnMap, array $attributes = []): LeadImport
    {
        Storage::fake('local');
        Storage::disk('local')->put('lead-imports/test.csv', file_get_contents(base_path("tests/Fixtures/leads/{$fixture}")));

        return LeadImport::factory()->create([
            'path' => 'lead-imports/test.csv',
            'status' => LeadImportStatus::Queued,
            'column_map' => $columnMap,
            ...$attributes,
        ]);
    }

    it('imports valid rows, skips invalid and duplicate ones, and queues AI for each lead', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $import = importFromFixture('leads.csv', ['name' => 0, 'email' => 1, 'company' => 2, 'job_title' => 3, 'company_size' => 4, 'budget' => 5, 'message' => 6]);
        $import->workspace->forceFill(['ideal_customer_profile' => 'Hotels and offices.'])->save();

        app()->call([new ImportLeads($import), 'handle']);

        $import->refresh();

        expect($import)
            ->status->toBe(LeadImportStatus::Completed)
            ->imported_rows->toBe(2)
            ->skipped_rows->toBe(2)
            ->and(collect($import->row_errors)->pluck('message')->all())->toBe([
                'The email field must be a valid email address.',
                'A lead with this email already exists.',
            ])
            ->and(Lead::where('email', 'dana@northwind-interiors.example')->sole())
            ->source->toBe(LeadSource::Csv)
            ->lead_import_id->toBe($import->id)
            ->job_title->toBe('Principal Designer');

        Queue::assertPushed(ScoreLead::class, 2);
        Storage::disk('local')->assertMissing('lead-imports/test.csv');
    });

    it('combines first and last names', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $import = importFromFixture('semicolon-bom.csv', ['first_name' => 0, 'last_name' => 1, 'email' => 2, 'company' => 3]);

        app()->call([new ImportLeads($import), 'handle']);

        expect(Lead::sole())->name->toBe('Lena Fischer')->company->toBe('Hausbau GmbH');
    });

    it('skips leads that already exist in the workspace', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $import = importFromFixture('semicolon-bom.csv', ['first_name' => 0, 'last_name' => 1, 'email' => 2]);
        Lead::factory()->for($import->workspace)->create(['email' => 'LENA@hausbau.example']);

        app()->call([new ImportLeads($import), 'handle']);

        expect($import->fresh()->skipped_rows)->toBe(1)
            ->and(Lead::count())->toBe(1);
    });

    it('stops at the demo row limit', function () {
        config(['demo.enabled' => true, 'demo.uploads.max_rows' => 1]);
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $import = importFromFixture('leads.csv', ['name' => 0, 'email' => 1]);

        app()->call([new ImportLeads($import), 'handle']);

        expect($import->fresh()->imported_rows)->toBe(1)
            ->and(collect($import->fresh()->row_errors)->last()['message'])->toBe('Stopped after 1 rows, the import limit.');
    });

    it('can import without running AI', function () {
        Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
        $import = importFromFixture('semicolon-bom.csv', ['first_name' => 0, 'email' => 2], ['score_leads' => false]);

        app()->call([new ImportLeads($import), 'handle']);

        expect(Lead::sole()->scoring_status->value)->toBe('skipped');
        Queue::assertNothingPushed();
    });

    it('marks the import as failed if the job crashes', function () {
        $import = LeadImport::factory()->create(['status' => LeadImportStatus::Processing]);

        (new ImportLeads($import))->failed(new RuntimeException('Disk full'));

        expect($import->fresh())
            ->status->toBe(LeadImportStatus::Failed)
            ->failure_message->toBe('The file could not be imported. Check that it is a valid CSV and try again.');
    });
});
