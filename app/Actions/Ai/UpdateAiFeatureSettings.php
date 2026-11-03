<?php

namespace App\Actions\Ai;

use App\Enums\AiFeature;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class UpdateAiFeatureSettings
{
    /**
     * @param  array<string, array{is_enabled: bool, auto_apply: bool, custom_instructions: string|null}>  $settingsByFeature  Keyed by AiFeature value.
     */
    public function handle(Workspace $workspace, array $settingsByFeature): void
    {
        DB::transaction(function () use ($workspace, $settingsByFeature): void {
            foreach ($settingsByFeature as $featureValue => $values) {
                $feature = AiFeature::from($featureValue);
                $setting = $workspace->aiSettingFor($feature);

                $setting->fill([
                    'is_enabled' => $values['is_enabled'],
                    'auto_apply' => $feature->supportsAutoApply() && $values['auto_apply'],
                    'custom_instructions' => $values['custom_instructions'],
                ])->save();
            }
        });

        $workspace->unsetRelation('aiFeatureSettings');
    }
}
