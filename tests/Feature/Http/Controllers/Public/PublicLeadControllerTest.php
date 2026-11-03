<?php

use App\Enums\AiTaskStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Jobs\DraftLeadFollowUp;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function leadSubmission(array $overrides = []): array
{
    return [
        'name' => 'Dana Whitfield',
        'email' => 'Dana@Northwind-Interiors.example',
        'company' => 'Northwind Interiors',
        'job_title' => 'Principal Designer',
        'company_size' => '11-50',
        'budget' => '$25k-$50k',
        'website' => 'https://northwind-interiors.example',
        'message' => 'Fitting out a 12-room boutique hotel in spring and need a trade supplier.',
        ...$overrides,
    ];
}

it('renders the lead form in the workspace\'s brand with the form options', function () {
    $workspace = Workspace::factory()->create(['brand_name' => 'Harbor & Pine']);

    get(route('public.leads.create', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/quote')
            ->where('brand.name', 'Harbor & Pine')
            ->has('companySizes', 5)
            ->has('budgets', 5));
});

it('creates a lead and queues scoring and a follow-up draft', function () {
    Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
    $workspace = Workspace::factory()->create(['ideal_customer_profile' => 'Hotels and designers furnishing 10+ rooms.']);

    post(route('public.leads.store', $workspace), leadSubmission())
        ->assertRedirect()
        ->assertInertiaFlash('leadSubmitted', true);

    $lead = Lead::sole();

    expect($lead)
        ->workspace_id->toBe($workspace->id)
        ->source->toBe(LeadSource::Form)
        ->status->toBe(LeadStatus::New)
        ->email->toBe('dana@northwind-interiors.example')
        ->score->toBeNull()
        ->scoring_status->toBe(AiTaskStatus::Pending);

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->lead->is($lead));
    Queue::assertPushed(DraftLeadFollowUp::class);
});

it('skips scoring with a hint until the ideal customer is described', function () {
    Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
    $workspace = Workspace::factory()->create(['ideal_customer_profile' => null]);

    post(route('public.leads.store', $workspace), leadSubmission());

    expect(Lead::sole())
        ->scoring_status->toBe(AiTaskStatus::Skipped)
        ->scoring_error->toBe('Add an ideal customer description in Settings → Lead scoring so the AI can score leads.');

    Queue::assertNotPushed(ScoreLead::class);
    Queue::assertPushed(DraftLeadFollowUp::class);
});

it('silently drops submissions that fill in the honeypot', function () {
    Queue::fake([ScoreLead::class, DraftLeadFollowUp::class]);
    $workspace = Workspace::factory()->create();

    post(route('public.leads.store', $workspace), leadSubmission(['fax_number' => '555-0100']))
        ->assertInertiaFlash('leadSubmitted', true);

    expect(Lead::count())->toBe(0);
});

it('validates the submission', function () {
    post(route('public.leads.store', Workspace::factory()->create()), leadSubmission([
        'company' => '',
        'website' => 'javascript:alert(1)',
        'company_size' => 'gigantic',
    ]))->assertSessionHasErrors([
        'company' => 'The company field is required.',
        'website' => 'The website field must be a valid URL.',
        'company_size' => 'The selected company size is invalid.',
    ]);
});
