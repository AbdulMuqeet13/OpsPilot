<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Ai\UpdateAiFeatureSettings;
use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAiFeatureSettingsRequest;
use App\Services\AI\AiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiFeatureController extends Controller
{
    public function edit(Request $request, AiGateway $gateway): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        $provider = (string) config('ai.provider');

        return Inertia::render('settings/ai-features', [
            'features' => array_map(function (AiFeature $feature) use ($workspace): array {
                $setting = $workspace->aiSettingFor($feature);

                return [
                    'key' => $feature->value,
                    'label' => $feature->label(),
                    'description' => $feature->description(),
                    'is_enabled' => $setting->is_enabled,
                    'supports_auto_apply' => $feature->supportsAutoApply(),
                    'auto_apply' => $setting->auto_apply,
                    'custom_instructions' => $setting->custom_instructions,
                ];
            }, AiFeature::configurable()),
            'provider' => [
                'name' => $provider,
                'model' => config('ai.model'),
                'hasApiKey' => filled(config("ai.providers.{$provider}.api_key")),
                'embeddingProvider' => config('ai.embedding_provider'),
                'embeddingModel' => config('ai.embedding_model'),
                'embeddingsEnabled' => $gateway->embeddingsEnabled(),
            ],
        ]);
    }

    public function update(UpdateAiFeatureSettingsRequest $request, UpdateAiFeatureSettings $updateAiFeatureSettings): RedirectResponse
    {
        $updateAiFeatureSettings->handle($request->user()->workspace, $request->settingsByFeature());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI settings saved.']);

        return back();
    }
}
