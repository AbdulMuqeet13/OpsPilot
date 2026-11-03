<?php

use App\Enums\AiFeature;
use App\Models\AiFeatureSetting;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function allFeaturesEnabled(array $overrides = []): array
{
    $features = [];

    foreach (AiFeature::configurable() as $feature) {
        $features[$feature->value] = ['is_enabled' => true, 'custom_instructions' => null];
    }

    return ['features' => array_replace_recursive($features, $overrides)];
}

describe('edit', function () {
    it('forbids agents', function () {
        actingAs(User::factory()->agent()->create())
            ->get(route('ai-features.edit'))
            ->assertForbidden();
    });

    it('lists only the configurable features with their saved settings', function () {
        $admin = User::factory()->create();
        AiFeatureSetting::factory()->for($admin->workspace)->disabled()->create(['feature' => AiFeature::DailyBrief]);

        actingAs($admin)
            ->get(route('ai-features.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/ai-features')
                ->has('features', count(AiFeature::configurable()))
                ->where('features.5.key', AiFeature::DailyBrief->value)
                ->where('features.5.is_enabled', false)
                ->where('features.0.is_enabled', true));
    });

    it('shows whether the provider key is configured without exposing it', function () {
        config(['ai.provider' => 'anthropic', 'ai.providers.anthropic.api_key' => 'sk-ant-secret']);

        actingAs(User::factory()->create())
            ->get(route('ai-features.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('provider.hasApiKey', true))
            ->assertDontSee('sk-ant-secret');
    });
});

describe('update', function () {
    it('forbids agents', function () {
        actingAs(User::factory()->agent()->create())
            ->put(route('ai-features.update'), allFeaturesEnabled())
            ->assertForbidden();
    });

    it('saves switches and custom instructions for the admin\'s workspace', function () {
        $admin = User::factory()->create();

        actingAs($admin)
            ->put(route('ai-features.update'), allFeaturesEnabled([
                AiFeature::LeadScoring->value => ['is_enabled' => false],
                AiFeature::ReplyDraft->value => ['custom_instructions' => '  Sign off as The Oakline Team.  '],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $workspace = $admin->workspace->fresh();

        expect($workspace->aiSettingFor(AiFeature::LeadScoring)->is_enabled)->toBeFalse()
            ->and($workspace->aiSettingFor(AiFeature::ReplyDraft)->custom_instructions)->toBe('Sign off as The Oakline Team.')
            ->and($workspace->aiSettingFor(AiFeature::TicketTriage)->is_enabled)->toBeTrue();
    });

    it('updates existing settings instead of duplicating them', function () {
        $admin = User::factory()->create();
        AiFeatureSetting::factory()->for($admin->workspace)->disabled()->create(['feature' => AiFeature::LeadScoring]);

        actingAs($admin)->put(route('ai-features.update'), allFeaturesEnabled());

        expect(AiFeatureSetting::where('feature', AiFeature::LeadScoring)->count())->toBe(1)
            ->and(AiFeatureSetting::where('feature', AiFeature::LeadScoring)->sole()->is_enabled)->toBeTrue();
    });

    it('does not touch other workspaces', function () {
        $otherWorkspace = Workspace::factory()->create();
        AiFeatureSetting::factory()->for($otherWorkspace)->disabled()->create(['feature' => AiFeature::LeadScoring]);

        actingAs(User::factory()->create())->put(route('ai-features.update'), allFeaturesEnabled());

        expect($otherWorkspace->fresh()->aiSettingFor(AiFeature::LeadScoring)->is_enabled)->toBeFalse();
    });

    it('rejects features that cannot be configured', function () {
        actingAs(User::factory()->create())
            ->put(route('ai-features.update'), ['features' => [AiFeature::ConnectionTest->value => ['is_enabled' => false]]])
            ->assertSessionHasErrors('features');
    });

    it('limits custom instructions to 1,000 characters', function () {
        actingAs(User::factory()->create())
            ->put(route('ai-features.update'), allFeaturesEnabled([
                AiFeature::ReplyDraft->value => ['custom_instructions' => str_repeat('a', 1001)],
            ]))
            ->assertSessionHasErrors(['features.reply_draft.custom_instructions' => 'Keep custom instructions under 1,000 characters.']);
    });
});
